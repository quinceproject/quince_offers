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

if (!defined('_PS_VERSION_')) {
    exit;
}

use Db;
use Quince\Offers\Settings\OfferSettings;
use Quince\Offers\Settings\OfferStore;

/**
 * Numer oferty.
 *
 * Postać: przedrostek, rok, miesiąc, kolejny numer w tym miesiącu, na przykład
 * `OF/2026/09/0001`. Licznik jest osobny dla każdego sklepu, więc dwa sklepy
 * w jednej instalacji mają dwa ciągi numerów, każdy bez dziur.
 *
 * Podbicie i odczyt idą jednym zapytaniem z `ON DUPLICATE KEY UPDATE`
 * i `LAST_INSERT_ID()`, żeby dwa zapytania wysłane w tej samej chwili nie
 * dostały tego samego numeru.
 */
final class OfferNumber
{
    public function __construct(private readonly OfferSettings $settings)
    {
    }

    public function next(int $idShop): string
    {
        $rok = (int) date('Y');
        $miesiac = (int) date('n');
        $przedrostek = $this->settings->get('PREFIX') ?: 'OF';

        return sprintf(
            '%s/%d/%02d/%04d',
            $przedrostek,
            $rok,
            $miesiac,
            $this->podbij($idShop, $rok, $miesiac)
        );
    }

    private function podbij(int $idShop, int $rok, int $miesiac): int
    {
        $tabela = _DB_PREFIX_ . OfferStore::TABLE_COUNTER;

        $db = Db::getInstance();

        // `LAST_INSERT_ID(wyrażenie)` zapamiętuje nadany numer w tym połączeniu.
        // Osobny SELECT po aktualizacji mógł przeczytać licznik podbity już
        // przez równoległe zapytanie i dwie oferty dostawały ten sam numer.
        $db->execute(
            'INSERT INTO `' . $tabela . '` (`id_shop`, `year`, `month`, `counter`)'
            . ' VALUES (' . $idShop . ', ' . $rok . ', ' . $miesiac . ', LAST_INSERT_ID(1))'
            . ' ON DUPLICATE KEY UPDATE `counter` = LAST_INSERT_ID(`counter` + 1)'
        );

        return (int) $db->Insert_ID();
    }
}
