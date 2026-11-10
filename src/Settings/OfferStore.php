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
 * Tabele modułu.
 *
 * Trzy, bo opisują trzy różne rzeczy: ofertę, jej pozycje i licznik numerów.
 *
 * Każda z nich ma `id_shop`, bo sklep w instalacji wielosklepowej ma własną
 * numerację, własny dziennik i własne oferty. Źródłowy moduł tego nie miał
 * i dwa sklepy czerpały numery z jednej puli, więc ciąg numerów w każdym
 * z nich był dziurawy.
 *
 * Pozycje leżą w osobnej tabeli, a nie w kolumnie z JSON-em, z dwóch powodów.
 * Po pierwsze przesyłka do CRM-u ma odtworzyć ofertę co do pozycji, więc i tak
 * trzeba je czytać pojedynczo. Po drugie oferta z konta klienta pokazuje jego
 * pozycje w tabeli, a nie w jednym polu tekstowym.
 */
final class OfferStore
{
    public const TABLE = 'quince_offer';
    public const TABLE_ITEM = 'quince_offer_item';
    public const TABLE_COUNTER = 'quince_offer_counter';

    /**
     * Podkatalog na gotowe pliki, wewnątrz `var/` sklepu.
     *
     * Nie w `img/`: tamten katalog serwuje serwer, więc plik z cenami dla
     * jednego klienta wisiałby pod adresem, który da się zgadnąć. `var/`
     * PrestaShop zamyka własnym `.htaccess`, a plik wychodzi wyłącznie przez
     * kontroler sprawdzający klucz.
     */
    public const PDF_SUBDIR = 'quince_offers';

    public function installTables(): bool
    {
        $silnik = _MYSQL_ENGINE_;

        $sql = [
            // `id_customer` zero znaczy „gość". Oferta gościa NIE trafia na żadne
            // konto, nawet jeśli podany adres pasuje do istniejącego klienta:
            // dopisanie jej po adresie wystawiłoby cudze ceny każdemu, kto zna
            // czyjś adres.
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                `id_offer` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                `id_lang` INT(11) UNSIGNED NOT NULL,
                `id_currency` INT(11) UNSIGNED NOT NULL,
                `number` VARCHAR(32) NOT NULL,
                `id_customer` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                `email` VARCHAR(255) NOT NULL,
                `customer_name` VARCHAR(255) NOT NULL DEFAULT "",
                `phone` VARCHAR(32) NOT NULL DEFAULT "",
                `total_net` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `total_gross` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `valid_until` DATE NULL DEFAULT NULL,
                `pdf_file` VARCHAR(255) NOT NULL DEFAULT "",
                -- Klucz do pobrania pliku. Plik leży poza katalogiem zgadywalnym
                -- z adresu, a odnośnik w mailu i w przesyłce do CRM-u niesie ten
                -- klucz zamiast ścieżki.
                `pdf_token` VARCHAR(64) NOT NULL DEFAULT "",
                `domain_email` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `newsletter` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `ip` VARCHAR(45) NOT NULL DEFAULT "",
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_offer`),
                UNIQUE KEY `uk_number` (`id_shop`, `number`),
                KEY `idx_email` (`email`),
                KEY `idx_customer` (`id_customer`),
                KEY `idx_date` (`date_add`)
            ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4',

            // Ceny zapisane, a nie liczone przy odczycie: oferta ma być wiążąca
            // w dniu wystawienia, a cena w katalogu zmienia się bez pytania.
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_ITEM . '` (
                `id_offer_item` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_offer` INT(11) UNSIGNED NOT NULL,
                `id_product` INT(11) UNSIGNED NOT NULL,
                `id_product_attribute` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                `reference` VARCHAR(64) NOT NULL DEFAULT "",
                `name` VARCHAR(255) NOT NULL DEFAULT "",
                `quantity` INT(11) UNSIGNED NOT NULL DEFAULT 1,
                `price_net` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `price_gross` DECIMAL(20,6) NOT NULL DEFAULT 0,
                -- Cena regularna (bez obniżek), do pokazania przekreślonej przy
                -- cenie z wyceny. Bez promocji równa `price_net`/`price_gross`.
                `price_regular_net` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `price_regular_gross` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `tax_rate` DECIMAL(10,3) NOT NULL DEFAULT 0,
                `discount` DECIMAL(20,6) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_offer_item`),
                KEY `idx_offer` (`id_offer`),
                KEY `idx_product` (`id_product`)
            ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4',

            // Licznik na sklep, rok i miesiąc. Klucz główny z trzech kolumn
            // zamiast własnego identyfikatora, bo to on niesie regułę: jeden
            // licznik na jeden miesiąc jednego sklepu.
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_COUNTER . '` (
                `id_shop` INT(11) UNSIGNED NOT NULL,
                `year` SMALLINT(4) UNSIGNED NOT NULL,
                `month` TINYINT(2) UNSIGNED NOT NULL,
                `counter` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_shop`, `year`, `month`)
            ) ENGINE=' . $silnik . ' DEFAULT CHARSET=utf8mb4',
        ];

        foreach ($sql as $zapytanie) {
            if (!Db::getInstance()->execute($zapytanie)) {
                return false;
            }
        }

        return true;
    }

    public function dropTables(): bool
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_ITEM . '`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_COUNTER . '`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }
}
