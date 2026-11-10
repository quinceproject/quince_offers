<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Quince\Offers\Settings\OfferSettings;
use Quince\Offers\Settings\OfferStore;
use Quince\Offers\Settings\PartnerLogos;

/**
 * Quince Offers — oferta PDF zamiast rozmowy o cenie w mailu.
 *
 * Klient kupujący na firmę rzadko wrzuca sprzęt za kilkanaście tysięcy do
 * koszyka. Prosi o ofertę, bo potrzebuje dokumentu z ceną netto, terminem
 * i numerem, który da się pokazać przełożonemu. Moduł wystawia taki dokument
 * od razu, zamiast kazać mu czekać na odpowiedź handlowca.
 *
 * Numer nadaje sklep i on trzyma własną kopię oferty — także wtedy, gdy CRM
 * jest niedostępny. Do CRM-u idzie powiadomienie przez nadajnik zdarzeń
 * silnika; to CRM jest księgą główną, ale sklep bez niego działa dalej.
 */
class Quince_Offers extends Module
{
    public function __construct()
    {
        $this->name = 'quince_offers';
        $this->tab = 'front_office_features';
        $this->version = '0.4.3';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.2.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        // Ekran modułu dziedziczy po kontrolerze silnika, a szablon po jego
        // layoucie. Bez tej deklaracji PrestaShop pozwoli wyłączyć silnik,
        // a moduł przestanie otwierać swój ekran bez żadnego ostrzeżenia.
        $this->dependencies = ['quince_engine'];

        // Jeden wpis w gałęzi „Quince project" w menu PrestaShopa, prowadzący
        // na ekran modułu. Podstron nie zgłaszamy: moduł ma je we własnym
        // bocznym pasku, a menu sklepu urosłoby wtedy o kilkanaście pozycji.
        // Kolejność wpisów w gałęzi ustala silnik.
        $this->tabs = [
            [
                'class_name' => 'AdminQuinceOffers',
                'visible' => true,
                'name' => 'Offers',
                'parent_class_name' => 'AdminQuinceProject',
                'route_name' => 'quince_offers',
                'wording' => 'Offers',
                'wording_domain' => 'Modules.Quinceoffers.Admin',
            ],
        ];

        parent::__construct();

        $this->displayName = $this->trans('Quince offers', [], 'Modules.Quinceoffers.Admin');
        $this->description = $this->trans(
            'Sends a PDF quote for the products a customer picks, with its own number and a copy kept in the shop.',
            [],
            'Modules.Quinceoffers.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Uninstall the module? The quote log, the numbering and the generated PDF files will be removed.',
            [],
            'Modules.Quinceoffers.Admin'
        );
    }

    public function install(): bool
    {
        if (!parent::install() || !(new OfferStore())->installTables()) {
            return false;
        }

        (new OfferSettings())->installDefaults();

        return $this->registerHook(['actionQuinceRegisterFeature', self::HOOK_OFFER]);
    }

    /**
     * Miejsce na przycisk i szufladę oferty.
     *
     * Hook własny, a nie natywny `displayProductAdditionalInfo`: o miejscu
     * decyduje mapa karty produktu w silniku, a natywny hook wstawiłby przycisk
     * tam, gdzie akurat stoi w motywie.
     */
    public const HOOK_OFFER = 'displayQuinceOffer';

    /** @param array<string,mixed> $params */
    public function hookDisplayQuinceOffer(array $params): string
    {
        $produkt = $params['product'] ?? null;
        $idProduct = 0;

        if (is_array($produkt) || $produkt instanceof ArrayAccess) {
            $idProduct = (int) ($produkt['id_product'] ?? 0);
        }

        if ($idProduct < 1) {
            return '';
        }

        $this->smarty->assign([
            'quinceOfferProduct' => $idProduct,
            'quinceOfferUrl' => $this->context->link->getModuleLink($this->name, 'submit', ['ajax' => 1]),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/offer.tpl');
    }

    public function uninstall(): bool
    {
        (new OfferSettings())->uninstall();
        (new PartnerLogos())->purge();

        return (new OfferStore())->dropTables() && parent::uninstall();
    }

    /**
     * Zgłoszenie do silnika: co dostarczamy i gdzie tego szukać.
     *
     * Po instalacji ożywa też element „Pobierz ofertę" na karcie produktu —
     * silnik zna jego miejsce, ale trzyma go wygaszonym, dopóki nie ma modułu,
     * który go obsłuży.
     *
     * @return array<int,array{type:string,id:string,label:string,route:string}>
     */
    public function hookActionQuinceRegisterFeature(): array
    {
        return [[
            'type' => 'offers',
            'id' => 'quince_offers',
            'label' => $this->trans('Offers', [], 'Modules.Quinceoffers.Admin'),
            'route' => 'quince_offers',
        ]];
    }

    /**
     * Przycisk „Konfiguruj" w menedżerze modułów.
     *
     * PrestaShop pokazuje go po samym istnieniu tej metody
     * (`ModuleRepository::__construct`, `method_exists($tmpModule, 'getContent')`),
     * a my przenosimy sprzedawcę na ekran modułu w panelu.
     */
    public function getContent(): void
    {
        \Tools::redirectAdmin($this->get('router')->generate('quince_offers'));
    }

}
