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

use Configuration;
use Language;
use Shop;

/**
 * Ustawienia modułu.
 *
 * Dwie rzeczy, których nie miał moduł źródłowy, a które są tu od początku.
 *
 * Wielojęzyczność: teksty, które klient czyta w mailu i w pliku PDF, mają
 * wartość na język. Sklep niemiecki nie dostaje polskiej klauzuli dlatego, że
 * ktoś kiedyś wpisał ją w jedyne pole, jakie było.
 *
 * Wielosklepowość: zapis idzie do każdego sklepu z bieżącego kontekstu, a nie
 * do wiersza globalnego. Powód jest ten sam, dla którego silnik ma `ConfigStore`:
 * sklep czyta najpierw swój wiersz, więc wartość zapisana „dla wszystkich"
 * nie docierała do sklepu, który miał już własny.
 */
final class OfferSettings
{
    private const PREFIX = 'QUINCE_OFFERS_';

    /** Ustawienia jednakowe dla wszystkich języków: wartość => wartość domyślna. */
    private const PLAIN = [
        'PREFIX' => 'OF',
        'VALIDITY_DAYS' => '30',
        // Kopie: identyfikatory kontaktow sklepu po przecinku. Adresy wpisywane
        // recznie odpadaja, bo kazdy z nich i tak musi gdzies zyc — a zyje
        // w Sklep -> Kontakty, razem z nazwiskiem i przypisaniem.
        'COPY_CONTACTS' => '',
        // Adres zwrotny: 0 znaczy „ten, z którego wysyłamy", a inna wartość to
        // identyfikator kontaktu sklepu. Nadawcą jest zawsze adres sklepu, bo
        // to on jest związany z pocztą wychodzącą.
        'REPLY_CONTACT' => '0',
    ];

    /** Teksty czytane przez klienta: wartość na każdy język sklepu. */
    private const TRANSLATED = [
        'EMAIL_SUBJECT',
        'EMAIL_BODY',
        'PDF_HEADER',
        'PDF_FOOTER',
        'PDF_NOTE',
    ];

    /**
     * Ustawienia jednakowe dla wszystkich języków.
     *
     * @return array<string,string>
     */
    public function plain(): array
    {
        $out = [];

        foreach (self::PLAIN as $klucz => $domyslna) {
            $wartosc = Configuration::get(self::PREFIX . $klucz);
            $out[$klucz] = ($wartosc === false || $wartosc === null) ? $domyslna : (string) $wartosc;
        }

        return $out;
    }

    public function get(string $klucz): string
    {
        return $this->plain()[$klucz] ?? '';
    }

    /**
     * Teksty w rozbiciu na języki, gotowe pod pola wielojęzyczne panelu.
     *
     * @return array<string,array<int,string>>
     */
    public function translated(): array
    {
        $out = [];

        foreach (self::TRANSLATED as $klucz) {
            $out[$klucz] = [];

            foreach (Language::getLanguages(true) as $jezyk) {
                $idLang = (int) $jezyk['id_lang'];
                $wartosc = Configuration::get(self::PREFIX . $klucz, $idLang);
                $out[$klucz][$idLang] = $wartosc === false ? '' : (string) $wartosc;
            }
        }

        return $out;
    }

    /** Tekst w jednym języku, gotowy do wysyłki. */
    public function text(string $klucz, int $idLang): string
    {
        $wartosc = Configuration::get(self::PREFIX . $klucz, $idLang);

        return $wartosc === false ? '' : (string) $wartosc;
    }

    /**
     * Zapis całego ekranu.
     *
     * @param array<string,string>            $plain
     * @param array<string,array<int,string>> $translated
     */
    public function save(array $plain, array $translated): void
    {
        foreach (self::PLAIN as $klucz => $_) {
            if (array_key_exists($klucz, $plain)) {
                $this->zapisz(self::PREFIX . $klucz, (string) $plain[$klucz]);
            }
        }

        foreach (self::TRANSLATED as $klucz) {
            if (!array_key_exists($klucz, $translated)) {
                continue;
            }

            $wartosci = [];

            foreach (Language::getLanguages(true) as $jezyk) {
                $idLang = (int) $jezyk['id_lang'];
                $wartosci[$idLang] = (string) ($translated[$klucz][$idLang] ?? '');
            }

            $this->zapisz(self::PREFIX . $klucz, $wartosci);
        }
    }

    /** Wartości domyślne przy instalacji: tylko tam, gdzie klucza jeszcze nie ma. */
    public function installDefaults(): void
    {
        foreach (self::PLAIN as $klucz => $domyslna) {
            if (!Configuration::hasKey(self::PREFIX . $klucz)) {
                $this->zapisz(self::PREFIX . $klucz, $domyslna);
            }
        }

        // Teksty zostają puste. Wpisane tu zdanie po angielsku wyglądałoby
        // w polskim sklepie na usterkę, a przetłumaczone „na wszelki wypadek"
        // trafiłoby do każdego języka jako ten sam napis.
    }

    public function uninstall(): void
    {
        foreach (array_keys(self::PLAIN) as $klucz) {
            Configuration::deleteByName(self::PREFIX . $klucz);
        }

        foreach (self::TRANSLATED as $klucz) {
            Configuration::deleteByName(self::PREFIX . $klucz);
        }
    }

    /**
     * Zapis do każdego sklepu z bieżącego kontekstu.
     *
     * @param string|array<int,string> $wartosc
     */
    private function zapisz(string $klucz, $wartosc): void
    {
        foreach (Shop::getContextListShopID() as $idShop) {
            Configuration::updateValue($klucz, $wartosc, false, null, (int) $idShop);
        }

        if (Shop::getContext() === Shop::CONTEXT_ALL) {
            Configuration::updateGlobalValue($klucz, $wartosc);
        }
    }
}
