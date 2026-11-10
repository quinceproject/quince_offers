<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Offers\Service;

use Configuration;
use Context;
use Db;
use Product;
use Quince\Offers\Settings\OfferSettings;
use Quince\Offers\Settings\OfferStore;
use Quince\Offers\Settings\PartnerLogos;
use Tools;

/**
 * Wystawienie oferty.
 *
 * Numer nadaje sklep, ceny zapisujemy w chwili wystawienia. Oferta ma być
 * wiążąca w dniu, w którym powstała, a cena w katalogu zmienia się bez pytania
 * — policzona ponownie przy odczycie znaczyłaby, że dokument mówi co innego
 * niż plik, który klient dostał mailem.
 *
 * Oferta gościa NIE trafia na żadne konto, nawet gdy podany adres pasuje do
 * istniejącego klienta. Dopisanie po adresie wystawiłoby cudze ceny każdemu,
 * kto zna czyjś adres; łączeniem leada z kontaktem zajmuje się CRM.
 */
final class OfferMaker
{
    public function __construct(
        private readonly OfferSettings $settings,
        private readonly OfferNumber $numbers
    ) {
    }

    /**
     * @param int[]                                                              $idProducts
     * @param array{email:string,name:string,phone:string,newsletter:bool}       $klient
     *
     * @return array<string,mixed>|null null, gdy z listy nie zostało nic do wyceny
     */
    public function create(array $idProducts, array $klient): ?array
    {
        $kontekst = Context::getContext();
        $idShop = (int) $kontekst->shop->id;
        $idLang = (int) $kontekst->language->id;

        $pozycje = $this->items($idProducts, $idLang, $idShop);

        if ($pozycje === []) {
            return null;
        }

        $numer = $this->numbers->next($idShop);
        $dni = max(1, (int) $this->settings->get('VALIDITY_DAYS'));

        $sumaNetto = 0.0;
        $sumaBrutto = 0.0;

        foreach ($pozycje as $pozycja) {
            $sumaNetto += $pozycja['price_net'] * $pozycja['quantity'];
            $sumaBrutto += $pozycja['price_gross'] * $pozycja['quantity'];
        }

        $oferta = [
            'id_shop' => $idShop,
            'id_lang' => $idLang,
            'id_currency' => (int) $kontekst->currency->id,
            'number' => $numer,
            // Zalogowany klient jedzie ze swoim identyfikatorem, gość z zerem.
            'id_customer' => (int) ($kontekst->customer->id ?? 0) > 0 && $kontekst->customer->isLogged()
                ? (int) $kontekst->customer->id
                : 0,
            'email' => $klient['email'],
            'customer_name' => $klient['name'],
            'phone' => $klient['phone'],
            'total_net' => $sumaNetto,
            'total_gross' => $sumaBrutto,
            'valid_until' => date('Y-m-d', strtotime('+' . $dni . ' days')),
            'pdf_file' => '',
            // Klucz losowy nadajemy od razu, choć plik dochodzi później: to on
            // trafia do odnośnika w mailu i do przesyłki dla CRM-u.
            'pdf_token' => bin2hex(random_bytes(16)),
            // Oferta partnerska: adres z domeny, dla której sklep ma logotyp.
            // Zapisane przy ofercie, bo lista partnerów zmienia się w czasie,
            // a dziennik ma mówić, jak dokument wyglądał w dniu wystawienia.
            'domain_email' => (new PartnerLogos())->isPartner($klient['email']) ? 1 : 0,
            'newsletter' => $klient['newsletter'] ? 1 : 0,
            'ip' => (string) Tools::getRemoteAddr(),
            'date_add' => date('Y-m-d H:i:s'),
        ];

        if (!Db::getInstance()->insert(OfferStore::TABLE, $this->escape($oferta))) {
            return null;
        }

        $idOffer = (int) Db::getInstance()->Insert_ID();

        foreach ($pozycje as $pozycja) {
            $pozycja['id_offer'] = $idOffer;
            Db::getInstance()->insert(OfferStore::TABLE_ITEM, $this->escape($pozycja));
        }

        $oferta['id_offer'] = $idOffer;
        $oferta['items'] = $pozycje;

        return $oferta;
    }

    /**
     * Pozycje oferty z katalogu.
     *
     * Produkt nieistniejący albo wyłączony wypada po cichu: lista bywa zbierana
     * przez kilka dni i sklep w tym czasie żyje.
     *
     * @param int[] $idProducts
     *
     * @return array<int,array<string,mixed>>
     */
    private function items(array $idProducts, int $idLang, int $idShop): array
    {
        $pozycje = [];

        foreach ($idProducts as $idProduct) {
            $produkt = new Product((int) $idProduct, false, $idLang, $idShop);

            if (!$produkt->id || !$produkt->active) {
                continue;
            }

            // Cena „jak w sklepie" — `getPriceStatic` domyślnie liczy z aktywną
            // promocją (`$usereduc` domyślnie `true`), więc bez zmian daje już
            // cenę po rabacie. Osobno dociągamy cenę regularną (`usereduc:
            // false`), żeby oferta i PDF mogły pokazać obie — bez promocji obie
            // wychodzą równe.
            $netto = (float) Product::getPriceStatic((int) $produkt->id, usetax: false);
            $brutto = (float) Product::getPriceStatic((int) $produkt->id, usetax: true);
            $regularnaNetto = (float) Product::getPriceStatic((int) $produkt->id, usetax: false, usereduc: false);
            $regularnaBrutto = (float) Product::getPriceStatic((int) $produkt->id, usetax: true, usereduc: false);

            $pozycje[] = [
                'id_product' => (int) $produkt->id,
                'id_product_attribute' => 0,
                'reference' => (string) $produkt->reference,
                'name' => (string) $produkt->name,
                'quantity' => 1,
                'price_net' => $netto,
                'price_gross' => $brutto,
                'price_regular_net' => $regularnaNetto,
                'price_regular_gross' => $regularnaBrutto,
                // Stawka wyliczona z dwóch cen, a nie odczytana z reguły podatkowej:
                // reguła zależy od kraju kupującego, a te dwie ceny już to
                // uwzględniają, bo pochodzą z tego samego wyliczenia.
                'tax_rate' => $netto > 0 ? round(($brutto / $netto - 1) * 100, 3) : 0.0,
                // Kwota netto promocji — różnica dwóch cen powyżej, nie osobne
                // wyliczenie. Zero, gdy produkt nie ma dziś promocji. CEP tego
                // pola nie potrzebuje (liczy rabat sam, §17.2), ale koszt
                // policzenia go poprawnie jest nulowy, więc nie zostaje fikcyjne.
                'discount' => round($regularnaNetto - $netto, 6),
            ];
        }

        return $pozycje;
    }

    /**
     * @param array<string,mixed> $wiersz
     *
     * @return array<string,mixed>
     */
    private function escape(array $wiersz): array
    {
        foreach ($wiersz as $kolumna => $wartosc) {
            if (is_string($wartosc)) {
                $wiersz[$kolumna] = pSQL($wartosc);
            }
        }

        return $wiersz;
    }

    /** Nazwa sklepu do maila i do przesyłki. */
    public function shopName(): string
    {
        return (string) Configuration::get('PS_SHOP_NAME');
    }
}
