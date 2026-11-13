<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Offers\Settings;

use Db;

/**
 * Dziennik ofert: odczyt dla panelu.
 *
 * Handlowiec przychodzi tu z jednym z dwóch pytań: „co wysłaliśmy temu
 * klientowi" albo „co poszło w zeszłym tygodniu". Stąd filtr po adresie
 * i numerze w jednym polu oraz zakres dat, a nie rozbudowana wyszukiwarka.
 *
 * Zapis ofert leży w `OfferMaker`; tutaj jest wyłącznie czytanie.
 */
final class OfferLog
{
    /** Ile wierszy na stronę. Tyle mieści się bez przewijania na typowym ekranie. */
    public const PER_PAGE = 30;

    /** @var string[] kolumny, po których wolno sortować */
    private const SORTABLE = ['number', 'date_add', 'email', 'total_net'];

    /**
     * @param array{search:string,from:string,to:string} $filtry
     *
     * @return array<int,array<string,mixed>>
     */
    public function rows(array $filtry, int $strona, string $sortuj, string $kierunek): array
    {
        $sortuj = in_array($sortuj, self::SORTABLE, true) ? $sortuj : 'date_add';
        $kierunek = strtoupper($kierunek) === 'ASC' ? 'ASC' : 'DESC';
        $odsuniecie = max(0, ($strona - 1) * self::PER_PAGE);

        $sql = 'SELECT o.*, s.name AS shop_name, l.name AS lang_name, c.iso_code AS currency,'
            . ' (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . OfferStore::TABLE_ITEM . '` i WHERE i.id_offer = o.id_offer) AS items'
            . ' FROM `' . _DB_PREFIX_ . OfferStore::TABLE . '` o'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.id_shop = o.id_shop'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'lang` l ON l.id_lang = o.id_lang'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'currency` c ON c.id_currency = o.id_currency'
            . $this->where($filtry)
            . ' ORDER BY o.`' . bqSQL($sortuj) . '` ' . $kierunek
            . ' LIMIT ' . self::PER_PAGE . ' OFFSET ' . $odsuniecie;

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /** @param array{search:string,from:string,to:string} $filtry */
    public function count(array $filtry): int
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . OfferStore::TABLE . '` o' . $this->where($filtry)
        );
    }

    /**
     * Pozycje ofert z jednej strony wyników, zebrane pod identyfikatorem oferty.
     *
     * Jedno zapytanie na całą stronę, a nie jedno na wiersz: przy trzydziestu
     * ofertach to różnica między jednym a trzydziestoma zapytaniami.
     *
     * @param int[] $idOffers
     *
     * @return array<int,array<int,array<string,mixed>>>
     */
    public function itemsFor(array $idOffers): array
    {
        if ($idOffers === []) {
            return [];
        }

        $lista = implode(',', array_map('intval', $idOffers));

        $wiersze = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . OfferStore::TABLE_ITEM . '`'
            . ' WHERE id_offer IN (' . $lista . ') ORDER BY id_offer_item'
        ) ?: [];

        $out = [];

        foreach ($wiersze as $wiersz) {
            $out[(int) $wiersz['id_offer']][] = $wiersz;
        }

        return $out;
    }

    /**
     * Jedna oferta razem z pozycjami, w postaci, jakiej oczekuje generator.
     *
     * @return array<string,mixed>|null
     */
    public function one(int $idOffer): ?array
    {
        $oferta = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . OfferStore::TABLE . '` WHERE id_offer = ' . $idOffer
        );

        if (!$oferta) {
            return null;
        }

        $oferta['items'] = $this->itemsFor([$idOffer])[$idOffer] ?? [];

        return $oferta;
    }

    /** Zapisanie nazwy pliku przy ofercie. */
    public function setFile(int $idOffer, string $plik): void
    {
        Db::getInstance()->update(
            OfferStore::TABLE,
            ['pdf_file' => pSQL($plik)],
            'id_offer = ' . $idOffer
        );
    }

    /**
     * Ile ofert i na jaką kwotę: nagłówek ekranu.
     *
     * Kwota policzona OSOBNO DLA KAŻDEJ WALUTY. Sklep wielowalutowy wystawia
     * oferty w kilku walutach, a dodanie ich do siebie dałoby liczbę, która nie
     * znaczy nic, a wyglądałaby na kwotę w walucie domyślnej.
     *
     * @return array{total:int,month:int,sums:array<int,array{currency:string,sum:float}>}
     */
    public function stats(): array
    {
        $tabela = '`' . _DB_PREFIX_ . OfferStore::TABLE . '`';
        $odPoczatkuMiesiaca = date('Y-m-01 00:00:00');

        $sumy = Db::getInstance()->executeS(
            'SELECT c.iso_code AS currency, SUM(o.total_net) AS sum'
            . ' FROM ' . $tabela . ' o'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'currency` c ON c.id_currency = o.id_currency'
            . ' WHERE o.date_add >= "' . pSQL($odPoczatkuMiesiaca) . '"'
            . ' GROUP BY o.id_currency'
        ) ?: [];

        return [
            'total' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . $tabela),
            'month' => (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM ' . $tabela . ' WHERE date_add >= "' . pSQL($odPoczatkuMiesiaca) . '"'
            ),
            'sums' => array_map(static fn (array $w): array => [
                'currency' => (string) $w['currency'],
                'sum' => (float) $w['sum'],
            ], $sumy),
        ];
    }

    /** @param array{search:string,from:string,to:string} $filtry */
    private function where(array $filtry): string
    {
        $warunki = [];

        if ($filtry['search'] !== '') {
            $szukane = pSQL($filtry['search']);
            // Jedno pole na numer, adres i nazwę: handlowiec ma pod ręką jedną
            // z tych trzech rzeczy i nie chce zgadywać, w którą rubrykę ją wpisać.
            $warunki[] = '(o.number LIKE "%' . $szukane . '%"'
                . ' OR o.email LIKE "%' . $szukane . '%"'
                . ' OR o.customer_name LIKE "%' . $szukane . '%")';
        }

        if ($filtry['from'] !== '') {
            $warunki[] = 'o.date_add >= "' . pSQL($filtry['from']) . ' 00:00:00"';
        }

        if ($filtry['to'] !== '') {
            $warunki[] = 'o.date_add <= "' . pSQL($filtry['to']) . ' 23:59:59"';
        }

        return $warunki === [] ? '' : ' WHERE ' . implode(' AND ', $warunki);
    }
}
