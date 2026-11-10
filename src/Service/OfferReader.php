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

use Db;
use DbQuery;
use Quince\Offers\Settings\OfferStore;

/**
 * Odczyt wystawionych ofert, do Admin API.
 *
 * `OfferMaker` tylko zapisuje; to jedyne miejsce, które czyta ofertę
 * z powrotem. Oferta nigdy się nie aktualizuje po wystawieniu (ceny
 * zamrożone, bez statusu — patrz komentarz przy `OfferStore`), więc kursor
 * po `id_offer` (ostry, rosnąco) wystarcza — nie trzeba `date_upd`.
 */
final class OfferReader
{
    /** @return array<string,mixed>|null */
    public function get(int $idOffer, int $idShop): ?array
    {
        $query = (new DbQuery())
            ->select('*')
            ->from(OfferStore::TABLE)
            ->where('id_offer = ' . $idOffer)
            ->where('id_shop = ' . $idShop);

        $row = Db::getInstance()->getRow($query);

        return is_array($row) ? $row : null;
    }

    /**
     * Oferty nowsze niż `$sinceId`, od najstarszej do najnowszej.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listSinceId(int $idShop, int $sinceId, int $limit, int $offset): array
    {
        $query = (new DbQuery())
            ->select('*')
            ->from(OfferStore::TABLE)
            ->where('id_shop = ' . $idShop)
            ->where('id_offer > ' . $sinceId)
            ->orderBy('id_offer ASC')
            ->limit($limit, $offset);

        $rows = Db::getInstance()->executeS($query);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function items(int $idOffer): array
    {
        $query = (new DbQuery())
            ->select('*')
            ->from(OfferStore::TABLE_ITEM)
            ->where('id_offer = ' . $idOffer)
            ->orderBy('id_offer_item ASC');

        $rows = Db::getInstance()->executeS($query);

        return is_array($rows) ? $rows : [];
    }
}
