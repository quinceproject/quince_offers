<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Offers\Controller\Admin;

use Quince\Engine\Controller\Admin\QuinceController;

/**
 * Wspólna podstawa ekranów modułu.
 *
 * Trzyma spis zakładek w jednym miejscu: nowy widok dopisany tylko w jednym
 * kontrolerze dawałby ekran bez zakładki, do którego nie da się wrócić.
 */
abstract class OffersBaseController extends QuinceController
{
    protected const DOMENA = 'Modules.Quinceoffers.Admin';

    /**
     * @return array<int,array{label:string,route:string}>
     */
    protected function tabs(): array
    {
        return [
            ['label' => 'Settings', 'route' => 'quince_offers'],
            ['label' => 'Quote log', 'route' => 'quince_offers_log'],
            ['label' => 'Partner logos', 'route' => 'quince_offers_logos'],
        ];
    }

    /**
     * Parametry wspólne dla obu widoków: nagłówek, zakładki i podświetlenie
     * pozycji w bocznym pasku.
     *
     * @return array<string,mixed>
     */
    protected function chrome(string $route): array
    {
        return [
            'layoutTitle' => 'Offers',
            'layoutTitleDomain' => self::DOMENA,
            'quinceTabs' => $this->tabs(),
            'quinceTabsDomain' => self::DOMENA,
            // Boczny pasek podświetla pozycję modułu, która wskazuje ustawienia.
            // Dziennik jest jego widokiem, a nie osobną pozycją w menu.
            'quinceRoute' => $route,
            'quinceNavRoute' => 'quince_offers',
        ];
    }
}
