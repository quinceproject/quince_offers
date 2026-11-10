<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Offers\ApiPlatform\Resources;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Quince\Offers\ApiPlatform\Action\OfferPdfAction;
use Quince\Offers\ApiPlatform\Provider\OfferPdfProvider;
use Quince\Offers\ApiPlatform\Provider\OfferProvider;

/**
 * Wystawiona oferta (wycena) w Admin API.
 *
 * Tylko do odczytu: oferta nie ma statusu i nie zmienia się po wystawieniu
 * (ceny zamrożone w dniu wystawienia — patrz `OfferStore`), więc nie ma tu
 * żadnego zapisu, w żadną stronę. Dokładnie kształt uzgodniony z CEP.
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/quince-offers/offers',
            provider: OfferProvider::class,
            extraProperties: ['scopes' => ['quince_offers_read']],
            openapiContext: [
                'summary' => 'Quotes issued since a given one',
                'description' => 'Filter with `sinceId` and `shopId`. Page with `page` and `limit` (50 by '
                    . 'default, 200 at most), ordered oldest-first. Take the `id` of the last item on the page '
                    . 'as `sinceId` for the next call — the filter is strict (`>`), so that item never repeats.',
            ],
        ),
        new Get(
            uriTemplate: '/quince-offers/offers/{id}',
            provider: OfferProvider::class,
            extraProperties: ['scopes' => ['quince_offers_read']],
            openapiContext: ['summary' => 'One quote by its id'],
        ),
        new Get(
            uriTemplate: '/quince-offers/offers/{id}/pdf',
            controller: OfferPdfAction::class,
            provider: OfferPdfProvider::class,
            output: false,
            extraProperties: ['scopes' => ['quince_offers_read']],
            openapiContext: [
                'summary' => 'The quote PDF file',
                'description' => 'Binary `application/pdf`, not JSON. Unlike the link in the shop (`pdfUrl`), '
                    . 'this endpoint does not require a front-office session matching the quote\'s customer — '
                    . 'OAuth2 is the only check. `404` when the quote has no file yet, or belongs to another shop.',
            ],
        ),
    ],
)]
class Offer
{
    #[ApiProperty(identifier: true)]
    public int $id = 0;

    public int $shopId = 0;

    /** Język klienta w chwili wyceny — język, w którym ma odpowiadać. */
    public int $langId = 0;

    /** Numer nadany przez sklep. */
    public string $number = '';

    /** ISO 8601 ze strefą. */
    public string $issuedAt = '';

    /** Data, bez godziny. */
    public string $validUntil = '';

    public string $currency = '';

    /**
     * `customerId` (`0` = gość), `email`, `name`, `phone`, `partnerDomain`
     * (kosmetyka PDF, nie zgoda), `newsletter` (gołe zaznaczenie formularza,
     * bez wersji klauzuli).
     *
     * @var array{customerId:int,email:string,name:string,phone:string,partnerDomain:bool,newsletter:bool}
     */
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'properties' => [
            'customerId' => ['type' => 'integer'],
            'email' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'partnerDomain' => ['type' => 'boolean'],
            'newsletter' => ['type' => 'boolean'],
        ],
    ])]
    public array $buyer = [];

    /**
     * Każda: `productId`, `productAttributeId` (dziś zawsze `0` — wybór
     * wariantu nie jest jeszcze możliwy w formularzu wyceny), `reference`,
     * `name`, `quantity`, `priceNet`/`priceGross` (cena, którą klient dostał
     * w wycenie — z promocją, jeśli była), `regularPriceNet`/
     * `regularPriceGross` (cena bez żadnych obniżek, zamrożona jak reszta
     * pozycji — równa cenie z wyceny, gdy promocji nie było), `taxRate`,
     * `discount` (różnica netto powyżej — CEP liczy rabat sam z dwóch cen,
     * to pole jest wygodą, nie wymogiem).
     *
     * @var array<int,array<string,mixed>>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'productId' => ['type' => 'integer'],
                'productAttributeId' => ['type' => 'integer'],
                'reference' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'quantity' => ['type' => 'integer'],
                'priceNet' => ['type' => 'number'],
                'priceGross' => ['type' => 'number'],
                'regularPriceNet' => ['type' => 'number'],
                'regularPriceGross' => ['type' => 'number'],
                'taxRate' => ['type' => 'number'],
                'discount' => ['type' => 'number'],
            ],
        ],
    ])]
    public array $items = [];

    public float $totalNet = 0.0;

    public float $totalGross = 0.0;

    /**
     * Odnośnik z kluczem jak dziś. Pusty, gdy złożenie pliku się nie udało.
     * Dla ofert gościa klucz sam wystarcza; dla oferty klienta zalogowanego
     * pobranie wymaga DODATKOWO aktywnej sesji front-office tego klienta —
     * zastrzeżenie nieobsłużone jeszcze przez samo dołożenie tego zasobu.
     */
    public string $pdfUrl = '';
}
