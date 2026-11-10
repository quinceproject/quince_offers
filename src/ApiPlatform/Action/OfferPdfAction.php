<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Offers\ApiPlatform\Action;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Oddanie pliku, który znalazł `OfferPdfProvider`.
 *
 * Kontroler niestandardowy: odpowiedź jest plikiem, nie zasobem do
 * serializacji. `data` jest tu zawsze tablicą — `null` (brak pliku albo
 * cudzy sklep) kończy się `404` wcześniej, w `ReadListener`, zanim ten
 * kontroler się odpali.
 */
final class OfferPdfAction
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        /** @var array{path:string,filename:string} $dane */
        $dane = $request->attributes->get('data');

        $response = new BinaryFileResponse($dane['path']);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $dane['filename']);

        return $response;
    }
}
