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

use Quince\Offers\Settings\PartnerLogos;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logotypy partnerów.
 *
 * Ekran robi trzy rzeczy: pokazuje listę, przyjmuje nowy plik i kasuje wpis.
 * Zapis jest natychmiastowy, bez wspólnego przycisku „zapisz" — plik i tak
 * wgrywa się osobnym żądaniem, a wtedy przycisk zbiorczy mówiłby nieprawdę
 * o tym, co jeszcze czeka na zapisanie.
 */
class LogosController extends OffersBaseController
{
    public function __construct(private readonly PartnerLogos $logos)
    {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_offers/views/templates/admin/logos.html.twig', [
            'logos' => $this->logos->rows(),
        ] + $this->chrome('quince_offers_logos'));
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_offers_logos_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_offers_logos');
        }

        $domena = (string) $request->request->get('domain', '');
        $plik = $request->files->get('logo');

        if ($this->logos->save($domena, $plik)) {
            $this->addFlash('success', $this->trans('Partner logo saved.', self::DOMENA));
        } else {
            $this->addFlash('error', $this->trans(
                'The logo could not be saved. Check the domain and the file: JPG, PNG or WEBP, up to 1 MB.',
                self::DOMENA
            ));
        }

        return $this->redirectToRoute('quince_offers_logos');
    }

    public function deleteAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_offers_logos_delete', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_offers_logos');
        }

        $this->logos->remove((string) $request->request->get('domain', ''));
        $this->addFlash('success', $this->trans('Partner logo removed.', self::DOMENA));

        return $this->redirectToRoute('quince_offers_logos');
    }
}
