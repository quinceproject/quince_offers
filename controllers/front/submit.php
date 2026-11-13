<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use Quince\Offers\Service\OfferList;
use Quince\Offers\Service\OfferMailer;
use Quince\Offers\Service\OfferMaker;
use Quince\Offers\Service\OfferNumber;
use Quince\Offers\Service\PdfGenerator;
use Quince\Offers\Settings\OfferSettings;
use Quince\Offers\Settings\OfferStore;

/**
 * Jedno wejście dla całej szuflady: lista ofertowa i wysyłka.
 *
 * Formularza nie sprawdzamy tutaj poza tym, co dotyczy samej oferty. Znacznik
 * strony, pułapkę, czas wypełnienia i limit powtórzeń bierze na siebie strażnik
 * silnika. Odpala się na `actionFrontControllerInitAfter` dla każdego żądania
 * niosącego jego pola i przerywa zanim ten kod się zacznie.
 */
class Quince_OffersSubmitModuleFrontController extends ModuleFrontController
{
    /** @var bool nie renderujemy strony, tylko JSON */
    public $ajax = true;

    public function postProcess(): void
    {
        $this->ajax = true;
    }

    public function displayAjax(): void
    {
        $this->odpowiedz($this->wykonaj((string) Tools::getValue('action', '')));
    }

    /** @return array<string,mixed> */
    private function wykonaj(string $akcja): array
    {
        $lista = new OfferList();

        switch ($akcja) {
            case 'add':
                $id = (int) Tools::getValue('id_product');
                $dodane = $lista->add($id);

                return ['ok' => true, 'added' => $dodane, 'items' => $this->pozycje($lista)];

            case 'remove':
                $lista->remove((int) Tools::getValue('id_product'));

                return ['ok' => true, 'items' => $this->pozycje($lista)];

            case 'list':
                return ['ok' => true, 'items' => $this->pozycje($lista)];

            case 'send':
                return $this->wyslij($lista);
        }

        return ['ok' => false, 'error' => 'unknown_action'];
    }

    /**
     * Wysyłka oferty.
     *
     * Produkt z karty, na której klient stoi, dokładamy do listy sam: klient,
     * który wypełnił formularz przy produkcie i nacisnął „wyślij", spodziewa
     * się oferty na TEN produkt, choćby nie nacisnął wcześniej „dodaj do listy".
     *
     * @return array<string,mixed>
     */
    private function wyslij(OfferList $lista): array
    {
        $email = trim((string) Tools::getValue('email'));

        if ($email === '' || !Validate::isEmail($email)) {
            return ['ok' => false, 'error' => 'email'];
        }

        $idProduct = (int) Tools::getValue('id_product');

        if ($idProduct > 0) {
            $lista->add($idProduct);
        }

        $produkty = $lista->all();

        if ($produkty === []) {
            return ['ok' => false, 'error' => 'empty'];
        }

        $ustawienia = new OfferSettings();
        $maker = new OfferMaker($ustawienia, new OfferNumber($ustawienia));

        $oferta = $maker->create($produkty, [
            'email' => $email,
            'name' => trim((string) Tools::getValue('customer_name')),
            'phone' => trim((string) Tools::getValue('phone')),
            'newsletter' => (bool) Tools::getValue('newsletter'),
        ]);

        if ($oferta === null) {
            return ['ok' => false, 'error' => 'empty'];
        }

        // Plik składamy przed wysyłką: mail ma iść z załącznikiem, a nie zapowiadać
        // ofertę, której jeszcze nie ma. Nieudane złożenie nie przerywa zapytania:
        // klient dostaje wtedy wiadomość bez załącznika, a sklep wpis w dzienniku.
        $plik = (new PdfGenerator($ustawienia))->build($oferta);

        if ($plik !== '') {
            $oferta['pdf_file'] = $plik;
            Db::getInstance()->update(
                OfferStore::TABLE,
                ['pdf_file' => pSQL($plik)],
                'id_offer = ' . (int) $oferta['id_offer']
            );
        }

        $wyslany = (new OfferMailer($ustawienia))->send($oferta);

        $this->zglosZdarzenie($oferta);
        $this->zapiszZgodeNaNewsletter($oferta);

        // Lista znika dopiero po wystawieniu oferty. Gdyby zniknęła wcześniej,
        // nieudana wysyłka zostawiłaby klienta z pustą listą i bez oferty.
        $lista->clear();

        return [
            'ok' => true,
            'number' => $oferta['number'],
            'mail' => $wyslany,
            // Odnośnik do pliku wraca do szuflady, żeby klient mógł go pobrać
            // od razu, nie czekając na wiadomość.
            'pdf' => $plik === '' ? '' : $this->context->link->getModuleLink(
                'quince_offers',
                'pdf',
                ['token' => $oferta['pdf_token']]
            ),
            'items' => [],
        ];
    }

    /**
     * Zgłoszenie zdarzenia `quote.created` na zewnątrz.
     *
     * Odbiorcę wskazuje sklep w module `quince_events`, w jego ekranie
     * ustawień. Ten moduł nie zna adresu odbiorcy. Bez ustawionego odbiorcy
     * nie dzieje się nic, a oferta i tak jest wystawiona.
     *
     * Przesyłka niesie tyle, żeby dało się odtworzyć ofertę bez zaglądania do
     * sklepu: numer, daty, waluta, kupujący, pozycje z cenami i adres pliku.
     * Plik idzie odnośnikiem z kluczem, a nie treścią, żeby koperta nie
     * zawierała megabajtów załącznika.
     *
     * @param array<string,mixed> $oferta
     */
    private function zglosZdarzenie(array $oferta): void
    {
        $zdarzenia = Module::getInstanceByName('quince_events');

        if (!$zdarzenia || !class_exists(\Quince\Events\Event\EventDispatcher::class)) {
            return;
        }

        $pozycje = [];

        foreach ($oferta['items'] ?? [] as $pozycja) {
            $pozycje[] = [
                'id_product' => (int) $pozycja['id_product'],
                'reference' => (string) $pozycja['reference'],
                'name' => (string) $pozycja['name'],
                'quantity' => (int) $pozycja['quantity'],
                'price_net' => round((float) $pozycja['price_net'], 6),
                'price_gross' => round((float) $pozycja['price_gross'], 6),
                'tax_rate' => round((float) $pozycja['tax_rate'], 3),
                'discount' => round((float) $pozycja['discount'], 6),
            ];
        }

        $waluta = new Currency((int) $oferta['id_currency']);

        (new \Quince\Events\Event\EventDispatcher(new \Quince\Events\Event\EventOutbox()))->dispatch(
            new \Quince\Events\Event\ShopEvent(
                type: 'quote.created',
                data: [
                    'number' => (string) $oferta['number'],
                    'issued_at' => (string) $oferta['date_add'],
                    'valid_until' => (string) $oferta['valid_until'],
                    'currency' => (string) $waluta->iso_code,
                    'buyer' => [
                        // Zero znaczy gość. Sklep nie zgaduje, kto to jest:
                        // dopasowaniem leada do kontaktu zajmuje się odbiorca.
                        'id_customer' => (int) $oferta['id_customer'],
                        'email' => (string) $oferta['email'],
                        'name' => (string) $oferta['customer_name'],
                        'phone' => (string) $oferta['phone'],
                        'partner_domain' => (bool) $oferta['domain_email'],
                        'newsletter' => (bool) $oferta['newsletter'],
                    ],
                    'items' => $pozycje,
                    'total_net' => round((float) $oferta['total_net'], 6),
                    'total_gross' => round((float) $oferta['total_gross'], 6),
                    'pdf_url' => (string) $oferta['pdf_file'] === '' ? '' : $this->context->link->getModuleLink(
                        'quince_offers',
                        'pdf',
                        ['token' => $oferta['pdf_token']]
                    ),
                ],
                context: [
                    'products' => array_map(
                        static fn (array $pozycja): int => (int) $pozycja['id_product'],
                        $pozycje
                    ),
                ],
                source: 'quince_offers'
            )
        );
    }

    /**
     * Zapis zgody na newsletter do rejestru `quince_cmp`, jeśli moduł jest
     * zainstalowany.
     *
     * Na samej ofercie zgoda jest tylko bitem, bez wersji klauzuli i skrótu
     * jej treści. `ConsentLog::record()` sam dociąga wersję zgody obowiązującą
     * teraz dla kodu `newsletter` i zapisuje ją razem ze zdarzeniem (ten sam
     * mechanizm co przy checkoucie).
     *
     * Zapisujemy zarówno zgodę, jak i jej brak: odmowa jest tak samo dowodem
     * jak przyzwolenie. Każde złożenie wyceny to osobne zaznaczenie pola, więc
     * duplikaty w logu nie są błędem.
     *
     * @param array<string,mixed> $oferta
     */
    private function zapiszZgodeNaNewsletter(array $oferta): void
    {
        $cmp = Module::getInstanceByName('quince_cmp');

        if (!$cmp || !class_exists(\Quince\Cmp\Storage\ConsentLog::class)) {
            return;
        }

        (new \Quince\Cmp\Storage\ConsentLog())->record(
            'newsletter',
            (bool) $oferta['newsletter'],
            (int) $oferta['id_shop'],
            (int) $oferta['id_lang'],
            [
                'email' => (string) $oferta['email'],
                'id_customer' => (int) $oferta['id_customer'],
                'source' => \Quince\Cmp\Storage\ConsentLog::SOURCE_NEWSLETTER,
                'form_id' => 'quince_offers',
            ]
        );
    }

    /**
     * Pozycje listy w postaci, w jakiej rysuje je szuflada.
     *
     * @return array<int,array<string,mixed>>
     */
    private function pozycje(OfferList $lista): array
    {
        $out = [];
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $waluta = $this->context->currency;
        $locale = $this->context->getCurrentLocale();

        foreach ($lista->all() as $idProduct) {
            $produkt = new Product($idProduct, false, $idLang, $idShop);

            if (!$produkt->id || !$produkt->active) {
                continue;
            }

            $okladka = Product::getCover((int) $produkt->id);
            $brutto = (float) Product::getPriceStatic((int) $produkt->id, true);

            $out[] = [
                'id_product' => (int) $produkt->id,
                'name' => (string) $produkt->name,
                'price' => $locale->formatPrice($brutto, (string) $waluta->iso_code),
                'image' => $this->context->link->getImageLink(
                    (string) $produkt->link_rewrite,
                    (int) ($okladka['id_image'] ?? 0),
                    'small_default'
                ),
            ];
        }

        return $out;
    }

    /** @param array<string,mixed> $dane */
    private function odpowiedz(array $dane): void
    {
        header('Content-Type: application/json');
        echo json_encode($dane);
        exit;
    }
}
