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

/**
 * 0.3.0 — cena regularna obok ceny z wyceny na pozycji oferty.
 *
 * Sprawdzane po nazwie kolumny, nie dodawane na ślepo: `ALTER TABLE ADD
 * COLUMN` na kolumnie, która już jest, wywraca się błędem (ten sam wzorzec
 * co w `quince_withdrawal/upgrade/upgrade-0.2.0.php`).
 *
 * @param Module $module
 */
function upgrade_module_0_3_0($module): bool
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'quince_offer_item';

    $istniejace = array_column(
        $db->executeS('SHOW COLUMNS FROM `' . $table . '`') ?: [],
        'Field'
    );

    $kolumny = [
        'price_regular_net' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
        'price_regular_gross' => 'DECIMAL(20,6) NOT NULL DEFAULT 0',
    ];

    foreach ($kolumny as $nazwa => $definicja) {
        if (in_array($nazwa, $istniejace, true)) {
            continue;
        }

        if (!$db->execute('ALTER TABLE `' . $table . '` ADD COLUMN `' . $nazwa . '` ' . $definicja)) {
            return false;
        }
    }

    // Pozycje zapisane wcześniej nie mają ceny regularnej — bez promocji
    // w tamtym dniu równa się dzisiejszej `price_net`/`price_gross`, więc to
    // bezpieczne wypełnienie, nie zgadywanie.
    return $db->execute(
        'UPDATE `' . $table . '` SET `price_regular_net` = `price_net`, `price_regular_gross` = `price_gross`'
        . ' WHERE `price_regular_net` = 0 AND `price_regular_gross` = 0'
    );
}
