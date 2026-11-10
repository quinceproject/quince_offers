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

use Quince\Offers\Settings\OfferSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ekran ofert.
 *
 * Na razie same ustawienia. Dziennik ofert i logotypy domen dochodzą tu jako
 * kolejne widoki tego samego ekranu, żeby moduł miał w bocznym pasku jedną
 * pozycję, a nie trzy.
 */
class OffersController extends OffersBaseController
{
    public function __construct(private readonly OfferSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_offers/views/templates/admin/offers.html.twig', [
            'settings' => $this->settings->plain(),
            'texts' => $this->settings->translated(),
            'languages' => $this->languages(),
            'senders' => $this->senders(),
        ] + $this->chrome('quince_offers'));
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_offers_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', self::DOMENA));

            return $this->redirectToRoute('quince_offers');
        }

        $this->settings->save(
            [
                'PREFIX' => trim((string) $request->request->get('PREFIX', '')),
                'VALIDITY_DAYS' => (string) max(1, (int) $request->request->get('VALIDITY_DAYS', 30)),
                // Pola wyboru przychodza jako tablica; zapisujemy sam ciag
                // identyfikatorow, zeby ustawienie zostalo jednym napisem.
                'COPY_CONTACTS' => implode(',', array_filter(array_map(
                    'intval',
                    (array) $request->request->all('COPY_CONTACTS')
                ))),
                'REPLY_CONTACT' => (string) max(0, (int) $request->request->get('REPLY_CONTACT', 0)),
            ],
            [
                'EMAIL_SUBJECT' => $this->wielojezyczne($request, 'EMAIL_SUBJECT'),
                'EMAIL_BODY' => $this->wielojezyczne($request, 'EMAIL_BODY'),
                'PDF_HEADER' => $this->wielojezyczne($request, 'PDF_HEADER'),
                'PDF_FOOTER' => $this->wielojezyczne($request, 'PDF_FOOTER'),
                'PDF_NOTE' => $this->wielojezyczne($request, 'PDF_NOTE'),
            ]
        );

        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quinceoffers.Admin'));

        return $this->redirectToRoute('quince_offers');
    }

    /**
     * Adresy, z których sklep może wysyłać.
     *
     * PrestaShop trzyma je w kontaktach sklepu, więc bierzemy je stamtąd zamiast
     * zakładać własną listę: adres dopisany w Sklep → Kontakty pojawia się tu sam.
     *
     * @return array<int,array{id:int,label:string}>
     */
    private function senders(): array
    {
        $idLang = (int) $this->getContext()->language->id;

        $lista = [[
            'id' => 0,
            'label' => (string) \Configuration::get('PS_SHOP_NAME') . ' — ' . (string) \Configuration::get('PS_SHOP_EMAIL'),
        ]];


        foreach (\Contact::getContacts($idLang) as $kontakt) {
            if (trim((string) $kontakt['email']) === '') {
                continue;
            }

            $lista[] = [
                'id' => (int) $kontakt['id_contact'],
                'label' => (string) $kontakt['name'] . ' — ' . (string) $kontakt['email'],
            ];
        }

        return $lista;
    }

    /**
     * Pola wielojęzyczne przychodzą z formularza jako `NAZWA_idJezyka`.
     *
     * @return array<int,string>
     */
    private function wielojezyczne(Request $request, string $klucz): array
    {
        $out = [];

        foreach ($this->languages() as $jezyk) {
            $idLang = (int) $jezyk['id_lang'];
            $out[$idLang] = trim((string) $request->request->get($klucz . '_' . $idLang, ''));
        }

        return $out;
    }
}
