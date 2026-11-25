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

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use Context;
use Currency;
use Db;
use Language;

/**
 * Wygląd maila z ofertą: kolory, fonty, stopka i bloki treści.
 *
 * Ten sam układ co maile `quince_contacts` (logo, pasek w kolorze sklepu,
 * biała karta, ciemna stopka), ale bez zależności od tamtego modułu: oferty
 * działają też w sklepie bez kontaktów. Kolory i fonty z ustawień wyglądu
 * silnika, dane sprzedawcy z Parametry sklepu → Kontakt → Sklepy.
 *
 * Bloki składamy tutaj, bo szablon maila nie ma pętli ani warunków: PrestaShop
 * podmienia w nim wyłącznie zmienne. Wszystko, co pochodzi od klienta albo
 * z katalogu, jest escapowane, bo `Mail::Send` niczego nie escapuje.
 */
final class OfferMailLayout
{
    /** Wartości domyślne silnika (AppearanceSettings), gdy silnika nie ma. */
    private const DEFAULT_PRIMARY = '#c99a3f';
    private const DEFAULT_DARK = '#262a33';
    private const DEFAULT_FONT_HEADING = 'Lexend';
    private const DEFAULT_FONT_BODY = 'Nunito Sans';

    private const TEXT = '#141414';
    private const MUTED = '#7a7a7a';
    private const BORDER = '#e3e3e0';

    private const FONT_FALLBACK = "'Segoe UI', Arial, Helvetica, sans-serif";

    private const DOMAIN = 'Modules.Quinceoffers.Shop';

    private readonly string $locale;

    public function __construct(private readonly Context $context, private readonly int $idLang)
    {
        $this->locale = (string) (Language::getLocaleById($idLang) ?: '');
    }

    /**
     * Wszystkie zmienne wyglądu i treści maila.
     *
     * @param array<string,mixed> $oferta
     *
     * @return array<string,string>
     */
    public function vars(array $oferta, string $wiadomoscSklepu, string $adresPdf): array
    {
        $glowny = $this->color('QUINCE_PRIMARY', self::DEFAULT_PRIMARY);
        $naglowki = $this->font('QUINCE_FONT_HEADING', self::DEFAULT_FONT_HEADING);
        $tekst = $this->font('QUINCE_FONT_BODY', self::DEFAULT_FONT_BODY);
        $waluta = $this->currency((int) ($oferta['id_currency'] ?? 0));

        return [
            '{color_primary}' => $glowny,
            '{color_on_primary}' => $this->textOn($glowny),
            '{color_dark}' => $this->color('QUINCE_DARK', self::DEFAULT_DARK),
            '{font_heading}' => $this->stack($naglowki),
            '{font_body}' => $this->stack($tekst),
            '{fonts_href}' => $this->fontsHref($naglowki, $tekst),
            '{company}' => $this->company(),
            '{shop_domain}' => $this->e((string) Configuration::get('PS_SHOP_DOMAIN')),
            '{valid_until_date}' => $this->e($this->date((string) ($oferta['valid_until'] ?? ''))),
            '{message_block}' => trim($wiadomoscSklepu) === ''
                ? ''
                : $this->block($wiadomoscSklepu, '0 0 24px'),
            '{summary_box}' => $this->summary($oferta),
            '{items_table}' => $this->items($oferta['items'] ?? [], $waluta, (float) $oferta['total_net'], (float) $oferta['total_gross']),
            '{pdf_button}' => $adresPdf === '' ? '' : $this->block($this->button($this->trans('Download the offer (PDF)'), $adresPdf, $glowny, $naglowki), '32px 0 12px')
                . $this->block($this->e($this->trans('The full offer in PDF is also attached to this message.')), '0', 'font-size:13px;line-height:20px;color:' . self::MUTED),
            '{expert_card}' => $this->expertCard(),
        ];
    }

    // ------------------------------------------------------------------ bloki

    /** @param array<string,mixed> $oferta */
    private function summary(array $oferta): string
    {
        $wiersze = [
            $this->trans('Offer number') => $this->e((string) $oferta['number']),
            $this->trans('Valid until') => $this->e($this->date((string) $oferta['valid_until'])),
        ];

        $html = '';

        foreach ($wiersze as $nazwa => $wartosc) {
            $html .= '<tr><td style="color:' . self::MUTED . ';padding:0 16px 0 0">' . $this->e($nazwa)
                . '</td><td style="font-weight:700">' . $wartosc . '</td></tr>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ' . self::BORDER . '"><tr><td style="padding:16px 20px">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:22px;color:' . self::TEXT . '">' . $html . '</table>'
            . '</td></tr></table>';
    }

    /** @param array<int,array<string,mixed>> $pozycje */
    private function items(array $pozycje, string $waluta, float $sumaNetto, float $sumaBrutto): string
    {
        $wiersze = '';

        foreach ($pozycje as $pozycja) {
            $ilosc = (int) $pozycja['quantity'];
            $brutto = (float) $pozycja['price_gross'];
            $netto = (float) $pozycja['price_net'];
            $nazwa = $this->e((string) $pozycja['name']);
            $idProduct = (int) ($pozycja['id_product'] ?? 0);

            if ($idProduct > 0) {
                $nazwa = '<a href="' . $this->e($this->context->link->getProductLink($idProduct, null, null, null, $this->idLang))
                    . '" style="font-weight:700;color:' . self::TEXT . ';text-decoration:none">' . $nazwa . '</a>';
            } else {
                $nazwa = '<span style="font-weight:700">' . $nazwa . '</span>';
            }

            $opis = [];
            $sku = trim((string) ($pozycja['reference'] ?? ''));

            if ($sku !== '') {
                $opis[] = $this->trans('SKU') . ' ' . $sku;
            }

            // Sama ilość: kwota jest raz, w prawej kolumnie.
            $opis[] = $ilosc . ' ' . $this->trans('pcs');

            $wiersze .= '<tr>'
                . '<td style="padding:12px 12px 12px 0;border-bottom:1px solid ' . self::BORDER . ';vertical-align:top">' . $nazwa
                . '<div style="color:' . self::MUTED . ';font-size:13px">' . $this->e(implode(' · ', $opis)) . '</div></td>'
                . '<td align="right" style="padding:12px 0;border-bottom:1px solid ' . self::BORDER . ';vertical-align:top;white-space:nowrap">'
                . '<div style="font-weight:700">' . $this->e($this->price($brutto * $ilosc, $waluta)) . '</div>'
                . '<div style="color:' . self::MUTED . ';font-size:13px">' . $this->e($this->price($netto * $ilosc, $waluta) . ' ' . $this->trans('net')) . '</div>'
                . '</td></tr>';
        }

        $wiersze .= '<tr><td style="padding:12px 12px 0 0;font-weight:700">' . $this->e($this->trans('Total')) . '</td>'
            . '<td align="right" style="padding:12px 0 0;white-space:nowrap">'
            . '<div style="font-weight:700">' . $this->e($this->price($sumaBrutto, $waluta) . ' ' . $this->trans('gross')) . '</div>'
            . '<div style="color:' . self::MUTED . ';font-size:13px">' . $this->e($this->price($sumaNetto, $waluta) . ' ' . $this->trans('net')) . '</div>'
            . '</td></tr>';

        return $this->label($this->trans('Items'))
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:22px;color:' . self::TEXT
            . ';border-top:1px solid ' . self::BORDER . '">' . $wiersze . '</table>';
    }

    /**
     * Osoba do kontaktu: ta sama co w PDF oferty, czyli pierwsza aktywna
     * z listy modułu kontaktów. Bez modułu blok znika.
     */
    private function expertCard(): string
    {
        $modul = \Module::getInstanceByName('quince_contacts');

        if (!$modul || !$modul->active || !class_exists(\Quince\Contacts\Settings\People::class)) {
            return '';
        }

        $osoba = null;

        foreach ((new \Quince\Contacts\Settings\People())->all($this->idLang) as $wiersz) {
            if (!empty($wiersz['active'])) {
                $osoba = $wiersz;
                break;
            }
        }

        if ($osoba === null) {
            return '';
        }

        $idContact = (int) ($osoba['id_contact'] ?? 0);
        $imie = (string) ($osoba['name'] ?? '');
        $stanowisko = (string) ($osoba['job_titles'][$this->idLang] ?? '');
        $email = (string) ($osoba['email'] ?? '');
        $telefon = $this->expertPhone($idContact);
        $zdjecie = (string) ($osoba['photo'] ?? '');

        $obraz = '';

        if ($zdjecie !== '' && is_file(_PS_IMG_DIR_ . 'quince/contacts/' . basename($zdjecie))) {
            $url = rtrim($this->context->shop->getBaseURL(true), '/') . '/img/quince/contacts/' . rawurlencode(basename($zdjecie));
            $obraz = '<td style="vertical-align:top;padding:0 16px 0 0"><img src="' . $this->e($url) . '" alt="' . $this->e($imie)
                . '" width="64" height="64" style="display:block;border:0;width:64px;height:64px;border-radius:50%;object-fit:cover"></td>';
        }

        $dane = '<div style="font-weight:700">' . $this->e($imie) . '</div>';

        if ($stanowisko !== '') {
            $dane .= $this->block($this->e($stanowisko), '0 0 6px', 'color:' . self::MUTED . ';font-size:13px;line-height:20px');
        }

        if ($telefon !== '') {
            $dane .= '<div><a href="tel:' . $this->e((string) preg_replace('/[^0-9+]/', '', $telefon)) . '" style="color:' . self::TEXT
                . ';font-weight:700;text-decoration:none">' . $this->e($telefon) . '</a></div>';
        }

        if ($email !== '') {
            $dane .= '<div><a href="mailto:' . $this->e($email) . '" style="color:' . self::TEXT . ';text-decoration:underline">'
                . $this->e($email) . '</a></div>';
        }

        return $this->label($this->trans('Questions? Talk to us'), '28px 0 8px')
            . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>' . $obraz
            . '<td style="vertical-align:top;font-size:15px;line-height:22px;color:' . self::TEXT . '">' . $dane . '</td>'
            . '</tr></table>';
    }

    /** Telefon osoby z pola dodatkowego encji kontaktu (jak w PDF). */
    private function expertPhone(int $idContact): string
    {
        if ($idContact <= 0) {
            return '';
        }

        try {
            $telefon = Db::getInstance()->getValue(
                'SELECT `quince_contacts_phone` FROM `' . _DB_PREFIX_ . 'contact_extra`'
                . ' WHERE id_contact = ' . $idContact
            );
        } catch (\Throwable) {
            return '';
        }

        return is_string($telefon) ? trim($telefon) : '';
    }

    /**
     * Przycisk. Wypełnienie i wysokość siedzą na komórce, nie na odnośniku:
     * Outlook na Windows (silnik Worda) pomija wypełnienie odnośnika i bez
     * tego rysuje sam tekst na wąskim pasku koloru.
     */
    private function button(string $tekst, string $adres, string $kolor, string $font): string
    {
        $styl = 'font-family:' . $this->stack($font) . ';font-size:13px;font-weight:700;letter-spacing:0.65px;text-transform:uppercase;color:'
            . $this->textOn($kolor) . ';text-decoration:none';

        return '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
            . '<td align="center" valign="middle" height="46" bgcolor="' . $kolor . '" style="background:' . $kolor
            . ';height:46px;padding:0 24px;mso-padding-alt:0 24px;' . $styl . '">'
            . '<a href="' . $this->e($adres) . '" style="display:inline-block;line-height:46px;' . $styl . '">' . $this->e($tekst) . '</a>'
            . '</td></tr></table>';
    }

    private function label(string $tekst, string $odstep = '24px 0 6px'): string
    {
        return $this->block(
            $this->e($tekst),
            $odstep,
            'font-size:12px;line-height:20px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;color:' . self::MUTED
        );
    }

    /**
     * Odstęp jako wypełnienie komórki tabeli. Outlook na Windows pomija
     * marginesy i wypełnienie bloków `div`, więc elementy się sklejają;
     * wypełnienie komórki stosuje każdy program pocztowy.
     */
    private function block(string $html, string $odstep, string $styl = ''): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:' . $odstep
            . ($styl !== '' ? ';' . $styl : '') . '">' . $html . '</td></tr></table>';
    }

    // ------------------------------------------------------------------ pomocnicze

    private function price(float $kwota, string $waluta): string
    {
        $locale = $this->context->getCurrentLocale();

        if ($locale === null || $waluta === '') {
            return number_format($kwota, 2, ',', ' ') . ($waluta !== '' ? ' ' . $waluta : '');
        }

        return $locale->formatPrice($kwota, $waluta);
    }

    /** Kod waluty oferty; gdy jej nie ma, waluta kontekstu. */
    private function currency(int $idCurrency): string
    {
        if ($idCurrency > 0) {
            $waluta = new Currency($idCurrency);

            if ($waluta->id) {
                return (string) $waluta->iso_code;
            }
        }

        return $this->context->currency ? (string) $this->context->currency->iso_code : '';
    }

    /**
     * Data tak, jak zapisuje się ją w języku oferty: po polsku 25.12.2026,
     * po angielsku 12/25/2026. Format daty języka w PrestaShopie bywa
     * techniczny (Y-m-d), a mail czyta człowiek. Bez rozszerzenia intl
     * zostaje format z ustawień języka.
     */
    private function date(string $data): string
    {
        $czas = strtotime($data);

        if ($czas === false) {
            return $data;
        }

        if ($this->locale !== '' && class_exists(\IntlDateFormatter::class)) {
            $f = new \IntlDateFormatter($this->locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
            // Krótki format skraca rok do dwóch cyfr; w ofercie rok ma być pełny.
            $f->setPattern((string) preg_replace('/y+/', 'y', (string) $f->getPattern()));
            $wynik = $f->format($czas);

            if (is_string($wynik) && $wynik !== '') {
                return $wynik;
            }
        }

        return date((string) ((new Language($this->idLang))->date_format_lite ?: 'Y-m-d'), $czas);
    }

    /** Dane sprzedawcy do stopki z Parametry sklepu → Kontakt → Sklepy. */
    private function company(): string
    {
        $nazwa = trim((string) Configuration::get('PS_SHOP_NAME'));
        $kod = trim((string) Configuration::get('PS_SHOP_CODE'));
        $miasto = trim((string) Configuration::get('PS_SHOP_CITY'));

        $czesci = array_values(array_filter([
            trim((string) Configuration::get('PS_SHOP_ADDR1')),
            trim((string) Configuration::get('PS_SHOP_ADDR2')),
            trim($kod . ' ' . $miasto),
        ], static fn (string $t): bool => $t !== ''));

        // Szczegóły rejestracji to pole wielowierszowe; w stopce idą w jednej linii.
        foreach (preg_split('/[\r\n]+/', (string) Configuration::get('PS_SHOP_DETAILS')) ?: [] as $linia) {
            $linia = trim($linia, " \t,;");

            if ($linia !== '') {
                $czesci[] = $linia;
            }
        }

        $reszta = $czesci === [] ? '' : '<span style="color:#ffffff">, ' . $this->e(implode(', ', $czesci)) . '</span>';

        return '<span style="color:#ffffff;font-weight:700">' . $this->e($nazwa) . '</span>' . $reszta;
    }

    private function trans(string $tekst): string
    {
        return $this->context->getTranslator()->trans($tekst, [], self::DOMAIN, $this->locale !== '' ? $this->locale : null);
    }

    /** Barwa z ustawień silnika, tylko w postaci #rrggbb. */
    private function color(string $klucz, string $domyslna): string
    {
        $wartosc = strtolower(trim((string) Configuration::get($klucz)));

        return preg_match('/^#[0-9a-f]{6}$/', $wartosc) ? $wartosc : $domyslna;
    }

    /** Tekst na tle przycisku: ciemny na jasnym, biały na ciemnym. */
    private function textOn(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(substr($hex, 1), 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#1c1c1c' : '#ffffff';
    }

    /** Nazwa fontu z ustawień silnika; tylko litery, cyfry i spacje. */
    private function font(string $klucz, string $domyslny): string
    {
        $nazwa = trim((string) Configuration::get($klucz));

        return preg_match('/^[A-Za-z0-9 ]{2,60}$/', $nazwa) ? $nazwa : $domyslny;
    }

    private function stack(string $font): string
    {
        return "'" . $font . "', " . self::FONT_FALLBACK;
    }

    /** Arkusz fontów z Google Fonts; bez niego program pocztowy weźmie fonty zapasowe. */
    private function fontsHref(string $naglowki, string $tekst): string
    {
        $rodziny = $tekst === $naglowki
            ? ['family=' . str_replace(' ', '+', $naglowki) . ':wght@400;700']
            : ['family=' . str_replace(' ', '+', $naglowki) . ':wght@700', 'family=' . str_replace(' ', '+', $tekst) . ':wght@400;700'];

        return $this->e('https://fonts.googleapis.com/css2?' . implode('&', $rodziny) . '&display=swap');
    }

    private function e(string $tekst): string
    {
        return htmlspecialchars($tekst, ENT_QUOTES, 'UTF-8');
    }
}
