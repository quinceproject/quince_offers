<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Offers\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Quince\Offers\Service\OfferReader;
use Quince\Offers\Settings\OfferStore;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plik oferty, do odpytywania Admin API — bez sprawdzania sesji front-office.
 *
 * `controllers/front/pdf.php` broni oferty klienta zalogowanego wymogiem,
 * żeby pobierał ją TEN SAM zalogowany klient — słuszne dla przeglądarki, ale
 * niewykonalne dla CEP, który czyta przez OAuth2, nie przez sesję klienta
 * Ten zasób broni się inaczej: scope `quince_offers_read`
 * i filtr po `id_shop`, tak jak reszta modułu.
 *
 * Zwraca gołą tablicę ze ścieżką i nazwą pliku, nie zasób `Offer` — ten
 * jeden wiersz czyta `OfferPdfAction`, a nie serializer, więc kształt nie
 * musi zgadzać się z resztą API Platform.
 *
 * @implements ProviderInterface<array<string,string>|null>
 */
final class OfferPdfProvider implements ProviderInterface
{
    public function __construct(
        private readonly OfferReader $offers,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     *
     * @return array{path:string,filename:string}|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?array
    {
        $idShop = $this->queryShopId();

        if ($idShop === null) {
            return null;
        }

        $row = $this->offers->get((int) ($uriVariables['id'] ?? 0), $idShop);

        if ($row === null) {
            return null;
        }

        $plik = (string) ($row['pdf_file'] ?? '');

        if ($plik === '') {
            return null;
        }

        $sciezka = _PS_ROOT_DIR_ . '/var/' . OfferStore::PDF_SUBDIR . '/' . basename($plik);

        if (!is_file($sciezka)) {
            return null;
        }

        return [
            'path' => $sciezka,
            'filename' => str_replace('/', '-', (string) $row['number']) . '.pdf',
        ];
    }

    private function queryShopId(): ?int
    {
        $shopId = $this->requestStack->getCurrentRequest()?->query->get('shopId');

        return $shopId !== null && $shopId !== '' ? (int) $shopId : null;
    }
}
