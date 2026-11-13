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

use Context;

/**
 * Lista ofertowa, czyli koszyk na wycenę.
 *
 * Klient zbiera produkty z kilku kart i dostaje JEDNĄ ofertę na wszystkie.
 * Lista żyje w ciasteczku sklepu, więc działa też dla gościa.
 *
 * Trzymamy same identyfikatory produktów. Nazwa, zdjęcie i cena i tak muszą
 * pochodzić z katalogu w chwili pokazania listy, bo cena zmienia się bez
 * pytania, a lista bywa otwarta dzień po dodaniu.
 */
final class OfferList
{
    private const KEY = 'quince_offer_list';

    /** @var int maksymalna liczba pozycji; więcej i tak nie zmieści się na jednej ofercie */
    private const LIMIT = 20;

    /** @return int[] */
    public function all(): array
    {
        $surowe = Context::getContext()->cookie->{self::KEY} ?? '';

        if (!is_string($surowe) || $surowe === '') {
            return [];
        }

        $lista = json_decode($surowe, true);

        if (!is_array($lista)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $lista))));
    }

    public function add(int $idProduct): bool
    {
        $lista = $this->all();

        if ($idProduct < 1 || in_array($idProduct, $lista, true) || count($lista) >= self::LIMIT) {
            return false;
        }

        $lista[] = $idProduct;
        $this->save($lista);

        return true;
    }

    public function remove(int $idProduct): void
    {
        $this->save(array_values(array_filter($this->all(), static fn (int $id): bool => $id !== $idProduct)));
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function clear(): void
    {
        $this->save([]);
    }

    /** @param int[] $lista */
    private function save(array $lista): void
    {
        $cookie = Context::getContext()->cookie;
        $cookie->{self::KEY} = json_encode(array_values($lista));
        $cookie->write();
    }
}
