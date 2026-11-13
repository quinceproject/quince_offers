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

use Quince\Offers\Service\PdfGenerator;
use Quince\Offers\Settings\OfferLog;
use Quince\Offers\Settings\OfferSettings;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Dziennik ofert.
 *
 * Osobna trasa, a nie przełącznik na ekranie ustawień: filtr, strona i sortowanie
 * muszą mieścić się w adresie, inaczej nie da się wrócić do tych samych wyników
 * ani wysłać ich komuś.
 */
class LogController extends OffersBaseController
{
    public function __construct(
        private readonly OfferLog $log,
        private readonly OfferSettings $settings
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $filtry = [
            'search' => trim((string) $request->query->get('search', '')),
            'from' => trim((string) $request->query->get('from', '')),
            'to' => trim((string) $request->query->get('to', '')),
        ];

        $strona = max(1, (int) $request->query->get('page', 1));
        $sortuj = (string) $request->query->get('sort', 'date_add');
        $kierunek = (string) $request->query->get('dir', 'DESC');

        $wszystkich = $this->log->count($filtry);
        $stron = max(1, (int) ceil($wszystkich / OfferLog::PER_PAGE));
        $strona = min($strona, $stron);

        $wiersze = $this->log->rows($filtry, $strona, $sortuj, $kierunek);
        $pozycje = $this->log->itemsFor(array_map(static fn (array $w): int => (int) $w['id_offer'], $wiersze));

        return $this->renderPanel('@Modules/quince_offers/views/templates/admin/log.html.twig', [
            'rows' => $wiersze,
            'items' => $pozycje,
            'total' => $wszystkich,
            'page' => $strona,
            'pages' => $stron,
            'sort' => $sortuj,
            'dir' => strtoupper($kierunek) === 'ASC' ? 'ASC' : 'DESC',
            'filters' => $filtry,
            'stats' => $this->log->stats(),
        ] + $this->chrome('quince_offers_log'));
    }

    /**
     * Pobranie pliku z panelu.
     *
     * Osobna droga niż ta dla klienta: sprzedawcę wpuszcza panel. Klucz oferty
     * by nie wystarczył, bo oferta wystawiona zalogowanemu klientowi wpuszcza
     * tylko jego.
     */
    public function fileAction(Request $request): Response
    {
        $oferta = $this->log->one((int) $request->query->get('id'));
        $plik = $oferta === null ? '' : (string) $oferta['pdf_file'];
        $sciezka = $plik === '' ? '' : PdfGenerator::directory() . basename($plik);

        if ($sciezka === '' || !is_file($sciezka)) {
            $this->addFlash('error', $this->trans('The file is gone. Build it again.', self::DOMENA));

            return $this->redirectToRoute('quince_offers_log');
        }

        $odpowiedz = new BinaryFileResponse($sciezka);
        $odpowiedz->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            str_replace('/', '-', (string) $oferta['number']) . '.pdf'
        );

        return $odpowiedz;
    }

    /**
     * Złożenie pliku dla oferty, która go nie ma.
     *
     * Dotyczy ofert sprzed generatora i tych, przy których składanie się nie
     * udało. Ceny i pozycje bierzemy z zapisanego wiersza, nie z katalogu:
     * dokument ma odtworzyć ofertę z dnia wystawienia, a nie dzisiejszy cennik.
     */
    public function buildAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_offers_log_build', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_offers_log');
        }

        $oferta = $this->log->one((int) $request->request->get('id'));

        if ($oferta === null) {
            return $this->redirectToRoute('quince_offers_log');
        }

        $plik = (new PdfGenerator($this->settings))->build($oferta);

        if ($plik === '') {
            $this->addFlash('error', $this->trans('The file could not be built.', self::DOMENA));
        } else {
            $this->log->setFile((int) $oferta['id_offer'], $plik);
            $this->addFlash('success', $this->trans('The file is ready.', self::DOMENA));
        }

        return $this->redirectToRoute('quince_offers_log');
    }
}
