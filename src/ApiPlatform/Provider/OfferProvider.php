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
use Currency;
use Link;
use Quince\Offers\ApiPlatform\Resources\Offer;
use Quince\Offers\Service\OfferReader;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Offer>
 */
final class OfferProvider implements ProviderInterface
{
    private const PER_PAGE = 50;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly OfferReader $offers,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param array<string,mixed> $uriVariables
     * @param array<string,mixed> $context
     *
     * @return array<int,Offer>|Offer|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Offer|null
    {
        $idShop = $this->queryShopId();

        if (isset($uriVariables['id'])) {
            $row = $idShop !== null ? $this->offers->get((int) $uriVariables['id'], $idShop) : null;

            return $row !== null ? $this->map($row) : null;
        }

        if ($idShop === null) {
            return [];
        }

        $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];

        $sinceId = max(0, (int) ($query['sinceId'] ?? 0));
        $limit = min(self::MAX_PER_PAGE, max(1, (int) ($query['limit'] ?? self::PER_PAGE)));
        $page = max(1, (int) ($query['page'] ?? 1));

        $rows = $this->offers->listSinceId($idShop, $sinceId, $limit, ($page - 1) * $limit);

        return array_map(fn (array $row): Offer => $this->map($row), $rows);
    }

    private function queryShopId(): ?int
    {
        $shopId = $this->requestStack->getCurrentRequest()?->query->get('shopId');

        return $shopId !== null && $shopId !== '' ? (int) $shopId : null;
    }

    /** @param array<string,mixed> $row */
    private function map(array $row): Offer
    {
        $idOffer = (int) $row['id_offer'];
        $idShop = (int) $row['id_shop'];
        $idLang = (int) $row['id_lang'];

        $wynik = new Offer();
        $wynik->id = $idOffer;
        $wynik->shopId = $idShop;
        $wynik->langId = $idLang;
        $wynik->number = (string) $row['number'];
        $wynik->issuedAt = $this->iso8601((string) $row['date_add']);
        $wynik->validUntil = (string) $row['valid_until'];
        $wynik->currency = (string) (new Currency((int) $row['id_currency']))->iso_code;
        $wynik->buyer = [
            'customerId' => (int) $row['id_customer'],
            'email' => (string) $row['email'],
            'name' => (string) $row['customer_name'],
            'phone' => (string) $row['phone'],
            'partnerDomain' => (bool) $row['domain_email'],
            'newsletter' => (bool) $row['newsletter'],
        ];
        $wynik->items = array_map(
            static fn (array $pozycja): array => [
                'productId' => (int) $pozycja['id_product'],
                'productAttributeId' => (int) $pozycja['id_product_attribute'],
                'reference' => (string) $pozycja['reference'],
                'name' => (string) $pozycja['name'],
                'quantity' => (int) $pozycja['quantity'],
                'priceNet' => (float) $pozycja['price_net'],
                'priceGross' => (float) $pozycja['price_gross'],
                'regularPriceNet' => (float) $pozycja['price_regular_net'],
                'regularPriceGross' => (float) $pozycja['price_regular_gross'],
                'taxRate' => (float) $pozycja['tax_rate'],
                'discount' => (float) $pozycja['discount'],
            ],
            $this->offers->items($idOffer)
        );
        $wynik->totalNet = (float) $row['total_net'];
        $wynik->totalGross = (float) $row['total_gross'];
        $wynik->pdfUrl = (string) $row['pdf_file'] === '' ? '' : (new Link())->getModuleLink(
            'quince_offers',
            'pdf',
            ['token' => (string) $row['pdf_token']],
            null,
            $idLang,
            $idShop
        );

        return $wynik;
    }

    /** Sklep liczy czas we własnej strefie; dla CEP dokładamy jawny offset, nie zgadujemy UTC. */
    private function iso8601(string $mysqlDateTime): string
    {
        $date = new \DateTime($mysqlDateTime, new \DateTimeZone(date_default_timezone_get()));

        return $date->format('c');
    }
}
