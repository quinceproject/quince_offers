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

use Configuration;
use Context;
use Product;
use Quince\Offers\Settings\OfferSettings;
use Quince\Offers\Settings\OfferStore;
use Quince\Offers\Settings\PartnerLogos;

/**
 * Złożenie pliku PDF z ofertą.
 *
 * Dokument jest arkuszem produktu, a nie fakturą: zdjęcie, opis, specyfikacja
 * i cena mają wystarczyć osobie, która nigdy nie była w sklepie, żeby podjąć
 * decyzję i pokazać ją przełożonemu. Układ przeniesiony z modułu, który to
 * robił wcześniej — sprawdził się w sprzedaży i nie ma powodu wymyślać go
 * od nowa.
 *
 * Ceny bierzemy z zapisanych pozycji oferty, a treść opisową z katalogu.
 * To nie jest niespójność: cena ma być ta z dnia wystawienia, bo dokument jest
 * wiążący, a opis i zdjęcie mają być aktualne, bo plik bywa składany ponownie
 * po miesiącach i nikt nie chce wtedy oglądać zdjęcia sprzed dwóch wersji.
 *
 * Biblioteka wczytuje się dopiero w chwili składania pliku. mPDF ciągnie za
 * sobą kilkadziesiąt klas i własny autoloader; wczytany przy każdym żądaniu
 * sklepu byłby kosztem płaconym za coś, czego klient używa raz na kilkaset
 * odsłon.
 *
 * Plik ląduje w `var/` sklepu, czyli poza katalogiem serwowanym przez serwer
 * (PrestaShop zamyka go własnym `.htaccess`). Do klienta wychodzi wyłącznie
 * przez kontroler, który sprawdza klucz.
 */
final class PdfGenerator
{
    /** Barwa napisów dokumentu. Ciemna, ale nie czarna: czerń na papierze brudzi. */
    private const INK = '#1a1a1a';

    public function __construct(
        private readonly OfferSettings $settings,
        private readonly PartnerLogos $logos = new PartnerLogos()
    ) {
    }

    /**
     * @param array<string,mixed> $oferta
     *
     * @return string nazwa pliku albo pusty napis, gdy złożenie się nie udało
     */
    public function build(array $oferta): string
    {
        $katalog = self::directory();

        if (!is_dir($katalog) && !@mkdir($katalog, 0755, true)) {
            return '';
        }

        require_once __DIR__ . '/../../libs/autoload.php';

        // Kody QR są osobnym pakietem mPDF-a, doinstalowanym obok. Rejestrujemy
        // go własnym autoloaderem, zamiast dopisywać do map wygenerowanych przez
        // Composera: te pliki nadpisuje każda kolejna instalacja zależności,
        // a ta jedna linia zostaje.
        spl_autoload_register(static function (string $klasa): void {
            $przedrostek = 'Mpdf\\QrCode\\';

            if (!str_starts_with($klasa, $przedrostek)) {
                return;
            }

            $plik = __DIR__ . '/../../libs/mpdf/qrcode/src/'
                . str_replace('\\', '/', substr($klasa, strlen($przedrostek))) . '.php';

            if (is_file($plik)) {
                require_once $plik;
            }
        });

        $nazwa = 'quote-' . preg_replace('/[^0-9a-zA-Z]+/', '-', (string) $oferta['number']) . '-'
            . substr((string) $oferta['pdf_token'], 0, 8) . '.pdf';

        try {
            $pdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 14,
                'margin_bottom' => 18,
                'margin_footer' => 8,
                // Katalog roboczy w `var/`, bo katalog biblioteki bywa tylko
                // do odczytu, a mPDF pisze tam pliki tymczasowe czcionek.
                'tempDir' => $katalog . 'tmp',
                'default_font' => 'dejavusanscondensed',
            ]);

            $pdf->SetTitle((string) $oferta['number']);
            $pdf->SetHTMLFooter($this->footer($oferta));
            $pdf->WriteHTML($this->html($oferta));
            $pdf->Output($katalog . $nazwa, \Mpdf\Output\Destination::FILE);
        } catch (\Throwable $blad) {
            \PrestaShopLogger::addLog('Quince Offers: ' . $blad->getMessage(), 3);

            return '';
        }

        return $nazwa;
    }

    /** Katalog z gotowymi plikami, poza katalogiem serwowanym przez serwer. */
    public static function directory(): string
    {
        return _PS_ROOT_DIR_ . '/var/' . OfferStore::PDF_SUBDIR . '/';
    }

    /**
     * Cały dokument.
     *
     * @param array<string,mixed> $oferta
     */
    private function html(array $oferta): string
    {
        $partner = (bool) ($oferta['domain_email'] ?? 0);
        $marka = $this->brandColour();

        $czesci = [$this->styles(), $this->header($oferta, $partner)];

        foreach (array_values($oferta['items'] ?? []) as $numer => $pozycja) {
            if ($numer > 0) {
                $czesci[] = '<hr class="sep">';
            }

            $czesci[] = $this->productSection($oferta, $pozycja, $partner, $marka);
        }

        $czesci[] = '<hr class="sep">';
        $czesci[] = $this->closing($oferta, $partner);

        return implode('', $czesci);
    }

    private function styles(): string
    {
        return '<style>'
            . 'body{font-family:dejavusanscondensed;font-size:9pt;color:' . self::INK . ';line-height:1.6}'
            . '.sep{border:none;border-top:0.2mm solid #e5e7eb;margin:9mm 0}'
            . '.meta{font-size:9pt;color:#6b7280;line-height:1.9}'
            . '.tiny{font-size:7.5pt;color:#9ca3af;line-height:1.8}'
            . '.band{background:#f7f7f8;padding:3mm 4mm;font-size:11pt;font-weight:bold;margin:9mm 0 3mm}'
            . '.spec{width:100%;border-collapse:collapse;margin:2mm 0}'
            . '.spec th{width:40%;text-align:left;padding:2mm 3mm;font-size:8.5pt;font-weight:600;'
            . 'color:#374151;background:#f9fafb;border-bottom:0.2mm solid #e5e7eb}'
            . '.spec td{padding:2mm 3mm;font-size:8.5pt;border-bottom:0.2mm solid #f3f4f6}'
            . '.combo{width:100%;border-collapse:collapse;margin:2mm 0}'
            . '.combo th{padding:2mm 3mm;font-size:8.5pt;font-weight:bold;color:#374151;'
            . 'background:#f9fafb;border-bottom:0.2mm solid #e5e7eb;text-align:left}'
            . '.combo td{padding:2mm 3mm;font-size:8.5pt;border-bottom:0.2mm solid #f3f4f6}'
            . '.desc{font-size:9pt;line-height:1.7;color:#374151}'
            . '.desc table{width:100%;border-collapse:collapse;margin:3mm 0}'
            . '.desc th,.desc td{padding:2mm 3mm;border:0.2mm solid #e5e7eb;font-size:8.5pt}'
            . '.card{border:0.2mm solid #e5e7eb}'
            . '</style>';
    }

    /**
     * Nagłówek: znak firmowy z lewej, numer i daty z prawej.
     *
     * Przy ofercie dla partnera znakiem jest jego logotyp, przy zwykłej —
     * logotyp sklepu.
     *
     * @param array<string,mixed> $oferta
     */
    private function header(array $oferta, bool $partner): string
    {
        $idLang = (int) $oferta['id_lang'];
        $naglowek = $this->settings->text('PDF_HEADER', $idLang);

        if ($naglowek === '') {
            $naglowek = $this->t('Quote', $idLang);
        }

        return '<table width="100%"><tr>'
            . '<td width="50%" valign="middle">' . $this->logo($oferta, $partner) . '</td>'
            . '<td width="50%" valign="top" align="right">'
            . '<div style="font-size:16pt;font-weight:bold">' . $this->e($naglowek) . '</div>'
            . '<div class="meta" style="margin-top:3mm">'
            . $this->t('No.', $idLang) . ': <b style="color:' . self::INK . '">'
            . $this->e((string) $oferta['number']) . '</b><br>'
            . $this->t('Issued', $idLang) . ': ' . $this->date((string) $oferta['date_add']) . '<br>'
            . $this->t('Valid until', $idLang) . ': <b style="color:' . self::INK . '">'
            . $this->date((string) $oferta['valid_until']) . '</b>'
            . '</div></td></tr></table>'
            . '<hr class="sep">';
    }

    /**
     * Jeden produkt: zdjęcie, opis, cena, specyfikacja, warianty i kontakt.
     *
     * @param array<string,mixed> $oferta
     * @param array<string,mixed> $pozycja
     */
    private function productSection(array $oferta, array $pozycja, bool $partner, string $marka): string
    {
        $idLang = (int) $oferta['id_lang'];
        $dane = $this->productData((int) $pozycja['id_product'], $idLang, (int) $oferta['id_shop']);

        $kody = array_filter([
            $dane['manufacturer'],
            $dane['reference'] === '' ? '' : 'SKU: ' . $dane['reference'],
            $dane['ean'] === '' ? '' : 'EAN: ' . $dane['ean'],
        ], static fn (string $czesc): bool => $czesc !== '');

        $prawa = '<table width="100%">'
            . '<tr><td style="padding-bottom:4mm;font-size:14pt;font-weight:bold;line-height:1.3">'
            . $this->e((string) $pozycja['name']) . '</td></tr>';

        if ($kody !== []) {
            $prawa .= '<tr><td style="padding-bottom:4mm;font-size:8pt;color:#9ca3af">'
                . $this->e(implode('   ·   ', $kody)) . '</td></tr>';
        }

        if ($dane['short'] !== '') {
            $prawa .= '<tr><td style="padding-bottom:5mm;font-size:9pt;color:#4b5563;line-height:1.7">'
                . $this->e($dane['short']) . '</td></tr>';
        }

        $ilosc = max(1, (int) $pozycja['quantity']);
        $netto = (float) $pozycja['price_net'] * $ilosc;
        $brutto = (float) $pozycja['price_gross'] * $ilosc;
        $regularnaBrutto = (float) ($pozycja['price_regular_gross'] ?? 0) * $ilosc;

        // Przekreślona cena regularna pojawia się tylko, gdy faktycznie różni
        // się od ceny z wyceny — epsilon zamiast `!==`, bo to porównanie liczb
        // zmiennoprzecinkowych po przejściu przez DECIMAL(20,6) w bazie.
        $maRabat = ($regularnaBrutto - $brutto) > 0.000001;

        $prawa .= '<tr><td style="padding-bottom:1mm;font-size:8.5pt;color:#9ca3af">'
            . $this->money($netto, $oferta) . ' ' . $this->t('net', $idLang) . '</td></tr>';

        if ($maRabat) {
            $prawa .= '<tr><td style="padding-bottom:1mm;font-size:10pt;color:#9ca3af">'
                . '<span style="text-decoration:line-through">' . $this->money($regularnaBrutto, $oferta) . '</span>'
                . '</td></tr>';
        }

        $prawa .= '<tr><td style="padding-bottom:5mm;font-size:20pt;font-weight:bold;line-height:1.2">'
            . $this->money($brutto, $oferta)
            . ' <span style="font-size:9pt;font-weight:normal;color:#9ca3af">'
            . $this->t('gross', $idLang) . '</span></td></tr>';

        if ($ilosc > 1) {
            $prawa .= '<tr><td style="padding-bottom:5mm;font-size:8.5pt;color:#9ca3af">'
                . $this->t('Quantity', $idLang) . ': ' . $ilosc . '</td></tr>';
        }

        // Wezwanie do sklepu odpada przy ofercie dla partnera: dokument idzie
        // wtedy dalej pod jego znakiem i odnośnik do naszego sklepu byłby w nim
        // ciałem obcym.
        if (!$partner && $dane['link'] !== '') {
            $prawa .= '<tr><td>'
                . '<table><tr><td style="background-color:' . $marka . ';padding:3mm 8mm">'
                . '<a href="' . $this->e($this->utm($dane['link'], 'button', (string) $oferta['number'])) . '"'
                . ' style="color:' . self::INK . ';text-decoration:none;font-size:9pt;font-weight:bold">'
                . $this->t('See it in the shop', $idLang) . ' &rarr;</a>'
                . '</td></tr></table></td></tr>';
        }

        $prawa .= '</table>';

        $sekcja = '<table width="100%"><tr>'
            . '<td width="38%" valign="top" style="padding-right:6mm">'
            . ($dane['image'] === '' ? '' : '<img src="' . $this->e($dane['image'])
                . '" style="max-width:100%;max-height:60mm;border:0.2mm solid #e5e7eb">')
            . '</td>'
            . '<td width="62%" valign="top">' . $prawa . '</td>'
            . '</tr></table>';

        if ($dane['description'] !== '') {
            $sekcja .= '<div class="band">' . $this->t('Product description', $idLang) . '</div>'
                . '<div class="desc">' . $dane['description'] . '</div>';
        }

        if ($dane['features'] !== []) {
            $sekcja .= '<div class="band">' . $this->t('Technical specification', $idLang) . '</div>'
                . $this->featuresTable($dane['features']);
        }

        if ($dane['combinations'] !== []) {
            $sekcja .= '<div class="band">' . $this->t('Variants', $idLang) . '</div>'
                . $this->combinationsTable($this->variantPrices($dane['combinations'], $pozycja), $oferta);
        }

        if (!$partner && $dane['link'] !== '') {
            $sekcja .= $this->contactBlock($oferta, $dane['link']);
        }

        return $sekcja;
    }

    /**
     * Kontakt i kod QR pod produktem.
     *
     * Kod prowadzi do karty produktu, więc oferta wydrukowana i położona na
     * biurku dalej prowadzi do sklepu. Osobę bierzemy z modułu kontaktów, jeśli
     * sklep go ma; bez niego zostaje adres i telefon sklepu.
     *
     * @param array<string,mixed> $oferta
     */
    private function contactBlock(array $oferta, string $link): string
    {
        $idLang = (int) $oferta['id_lang'];
        $osoba = $this->expert($idLang);

        $lewa = $osoba === null
            ? '<b>' . $this->e((string) Configuration::get('PS_SHOP_NAME')) . '</b><br>'
                . $this->e((string) Configuration::get('PS_SHOP_PHONE')) . '<br>'
                . $this->e((string) Configuration::get('PS_SHOP_EMAIL'))
            : '<b>' . $this->e($osoba['name']) . '</b>'
                . ($osoba['job'] === '' ? '' : '<br><span style="color:#6b7280">' . $this->e($osoba['job']) . '</span>')
                . ($osoba['phone'] === '' ? '' : '<br>' . $this->e($osoba['phone']))
                . ($osoba['email'] === '' ? '' : '<br>' . $this->e($osoba['email']));

        return '<div class="band">' . $this->t('Questions? Talk to us', $idLang) . '</div>'
            . '<table width="100%" class="card"><tr>'
            . '<td width="65%" valign="middle" style="padding:6mm;font-size:9pt">' . $lewa . '</td>'
            . '<td width="35%" valign="middle" align="center" style="padding:6mm">'
            . '<barcode code="' . $this->e($this->utm($link, 'qr_code', (string) $oferta['number']))
            . '" type="QR" class="barcode" size="1.2" error="M">'
            . '<div class="tiny" style="margin-top:2mm">' . $this->t('Scan to open the product', $idLang) . '</div>'
            . '</td></tr></table>';
    }

    /**
     * Zakończenie: zdanie sklepu, jego dane i klauzula.
     *
     * Dane firmy odpadają przy ofercie dla partnera — dokument idzie wtedy pod
     * jego znakiem, do jego klienta, i nasza stopka nie ma w nim czego szukać.
     *
     * @param array<string,mixed> $oferta
     */
    private function closing(array $oferta, bool $partner): string
    {
        $idLang = (int) $oferta['id_lang'];
        $stopka = $this->settings->text('PDF_FOOTER', $idLang);
        $klauzula = $this->settings->text('PDF_NOTE', $idLang);
        $out = '';

        if ($stopka !== '') {
            $out .= '<div style="font-size:8.5pt;color:#6b7280;margin-bottom:4mm">' . $this->e($stopka) . '</div>';
        }

        if (!$partner) {
            $adres = trim(implode(', ', array_filter([
                (string) Configuration::get('PS_SHOP_ADDR1'),
                (string) Configuration::get('PS_SHOP_ADDR2'),
                trim((string) Configuration::get('PS_SHOP_CODE') . ' ' . (string) Configuration::get('PS_SHOP_CITY')),
            ])));

            $czesci = array_filter([
                $adres,
                (string) Configuration::get('PS_SHOP_DETAILS'),
                (string) Configuration::get('PS_SHOP_PHONE'),
                (string) Configuration::get('PS_SHOP_EMAIL'),
            ]);

            $out .= '<div class="tiny">'
                . '<b style="color:#6b7280">' . $this->e((string) Configuration::get('PS_SHOP_NAME')) . '</b><br>'
                . $this->e(implode('  |  ', $czesci))
                . '</div>';
        }

        if ($klauzula !== '') {
            $out .= '<div class="tiny" style="margin-top:3mm;font-style:italic">' . nl2br($this->e($klauzula)) . '</div>';
        }

        return $out;
    }

    /**
     * Treść opisowa produktu z katalogu.
     *
     * @return array{image:string,manufacturer:string,reference:string,ean:string,short:string,description:string,link:string,features:array<int,array{name:string,value:string}>,combinations:array<int,array<string,mixed>>}
     */
    private function productData(int $idProduct, int $idLang, int $idShop): array
    {
        $produkt = new Product($idProduct, false, $idLang, $idShop);

        if (!$produkt->id) {
            return [
                'image' => '', 'manufacturer' => '', 'reference' => '', 'ean' => '',
                'short' => '', 'description' => '', 'link' => '', 'features' => [], 'combinations' => [],
            ];
        }

        $okladka = Product::getCover($idProduct);
        $idImage = (int) ($okladka['id_image'] ?? 0);

        // Ścieżka z dysku, a nie adres: mPDF pobierający obraz po sieci czekałby
        // na własny serwer, a przy zablokowanym ruchu wychodzącym nie dostałby nic.
        $sciezkaObrazu = $idImage > 0
            ? _PS_PRODUCT_IMG_DIR_ . implode('/', str_split((string) $idImage)) . '/' . $idImage . '.jpg'
            : '';

        $cechy = [];

        foreach ($produkt->getFrontFeatures($idLang) as $cecha) {
            $cechy[] = ['name' => (string) $cecha['name'], 'value' => (string) $cecha['value']];
        }

        return [
            'image' => is_file($sciezkaObrazu) ? $sciezkaObrazu : '',
            'manufacturer' => (string) $produkt->manufacturer_name,
            'reference' => (string) $produkt->reference,
            'ean' => (string) $produkt->ean13,
            'short' => trim(strip_tags((string) $produkt->description_short)),
            'description' => (string) $produkt->description,
            'link' => Context::getContext()->link->getProductLink($produkt),
            'features' => $cechy,
            'combinations' => $this->combinations($produkt, $idLang),
        ];
    }

    /**
     * Ceny wariantu liczone od ceny z oferty, a nie z katalogu.
     *
     * Powód techniczny: `Product::getPriceStatic` z wariantem wymaga koszyka
     * w kontekście, a plik bywa składany z panelu i z konsoli, gdzie koszyka
     * nie ma. Powód ważniejszy: dokument ma mówić o cenach z dnia wystawienia,
     * więc wariant liczymy jako cena pozycji plus wpływ wariantu.
     *
     * @param array<int,array<string,mixed>> $warianty
     * @param array<string,mixed>            $pozycja
     *
     * @return array<int,array<string,mixed>>
     */
    private function variantPrices(array $warianty, array $pozycja): array
    {
        $netto = (float) $pozycja['price_net'];
        $stawka = (float) $pozycja['tax_rate'];

        foreach ($warianty as $i => $wariant) {
            $wariantNetto = $netto + (float) $wariant['impact'];
            $warianty[$i]['net'] = $wariantNetto;
            $warianty[$i]['gross'] = $wariantNetto * (1 + $stawka / 100);
        }

        return $warianty;
    }

    /**
     * Warianty produktu z wpływem na cenę.
     *
     * @return array<int,array{name:string,reference:string,impact:float,net:float,gross:float}>
     */
    private function combinations(Product $produkt, int $idLang): array
    {
        $surowe = $produkt->getAttributeCombinations($idLang);

        if (!$surowe) {
            return [];
        }

        $zebrane = [];

        foreach ($surowe as $wiersz) {
            $id = (int) $wiersz['id_product_attribute'];

            if (!isset($zebrane[$id])) {
                $zebrane[$id] = [
                    'name' => [],
                    'reference' => (string) ($wiersz['reference'] ?? ''),
                    'impact' => (float) ($wiersz['price'] ?? 0),
                ];
            }

            $zebrane[$id]['name'][] = (string) $wiersz['attribute_name'];
        }

        $out = [];

        foreach ($zebrane as $wariant) {
            $out[] = [
                'name' => implode(' / ', $wariant['name']),
                'reference' => $wariant['reference'],
                'impact' => $wariant['impact'],
                'net' => 0.0,
                'gross' => 0.0,
            ];
        }

        return $out;
    }

    /**
     * Osoba do kontaktu z modułu kontaktów.
     *
     * @return array{name:string,job:string,phone:string,email:string}|null
     */
    private function expert(int $idLang): ?array
    {
        $modul = \Module::getInstanceByName('quince_contacts');

        if (!$modul || !$modul->active || !class_exists(\Quince\Contacts\Settings\People::class)) {
            return null;
        }

        foreach ((new \Quince\Contacts\Settings\People())->all($idLang) as $osoba) {
            if (!empty($osoba['active'])) {
                return [
                    'name' => (string) ($osoba['name'] ?? ''),
                    'job' => (string) ($osoba['job_title'] ?? ''),
                    'phone' => (string) ($osoba['phone'] ?? ''),
                    'email' => (string) ($osoba['email'] ?? ''),
                ];
            }
        }

        return null;
    }

    /** @param array<int,array{name:string,value:string}> $cechy */
    private function featuresTable(array $cechy): string
    {
        $wiersze = '';

        foreach ($cechy as $cecha) {
            $wiersze .= '<tr><th>' . $this->e($cecha['name']) . '</th>'
                . '<td>' . $this->e($cecha['value']) . '</td></tr>';
        }

        return '<table class="spec">' . $wiersze . '</table>';
    }

    /**
     * @param array<int,array<string,mixed>> $warianty
     * @param array<string,mixed>            $oferta
     */
    private function combinationsTable(array $warianty, array $oferta): string
    {
        $idLang = (int) $oferta['id_lang'];

        $wiersze = '<tr><th>' . $this->t('Variant', $idLang) . '</th>'
            . '<th>' . $this->t('Reference', $idLang) . '</th>'
            . '<th>' . $this->t('Price change', $idLang) . '</th>'
            . '<th>' . $this->t('Net', $idLang) . '</th>'
            . '<th>' . $this->t('Gross', $idLang) . '</th></tr>';

        foreach ($warianty as $wariant) {
            $wplyw = (float) $wariant['impact'];
            $opisWplywu = $wplyw > 0
                ? '+' . $this->money($wplyw, $oferta)
                : ($wplyw < 0 ? $this->money($wplyw, $oferta) : '-');

            $wiersze .= '<tr>'
                . '<td>' . $this->e((string) $wariant['name']) . '</td>'
                . '<td>' . $this->e((string) $wariant['reference']) . '</td>'
                . '<td>' . $opisWplywu . '</td>'
                . '<td>' . $this->money((float) $wariant['net'], $oferta) . '</td>'
                . '<td>' . $this->money((float) $wariant['gross'], $oferta) . '</td>'
                . '</tr>';
        }

        return '<table class="combo">' . $wiersze . '</table>';
    }

    /** @param array<string,mixed> $oferta */
    private function footer(array $oferta): string
    {
        return '<div style="font-size:7.5pt;color:#888;border-top:0.2mm solid #ddd;padding-top:1.5mm">'
            . '<table width="100%"><tr>'
            . '<td>' . $this->e((string) $oferta['number']) . '</td>'
            . '<td align="right">{PAGENO}/{nbpg}</td>'
            . '</tr></table></div>';
    }

    /**
     * Znak firmowy nagłówka.
     *
     * @param array<string,mixed> $oferta
     */
    private function logo(array $oferta, bool $partner): string
    {
        $sciezka = $partner ? $this->logos->fileFor((string) $oferta['email']) : '';

        if ($sciezka === '') {
            $sklepowe = _PS_IMG_DIR_ . (string) Configuration::get('PS_LOGO');
            $sciezka = is_file($sklepowe) ? $sklepowe : '';
        }

        return $sciezka === ''
            ? '<div style="font-size:13pt;font-weight:bold">'
                . $this->e((string) Configuration::get('PS_SHOP_NAME')) . '</div>'
            : '<img src="' . $this->e($sciezka) . '" style="max-height:16mm;max-width:60mm">';
    }

    /**
     * Odnośnik z oznaczeniem źródła.
     *
     * Bez tego wejście z oferty ginie w statystykach jako ruch bezpośredni
     * i nie widać, czy dokument w ogóle działa.
     */
    private function utm(string $adres, string $medium, string $numer): string
    {
        return $adres . (str_contains($adres, '?') ? '&' : '?') . http_build_query([
            'utm_source' => 'quote_pdf',
            'utm_medium' => $medium,
            'utm_campaign' => 'product_offer',
            'utm_content' => $numer,
        ]);
    }

    /**
     * Barwa marki z palety silnika.
     *
     * Silnik trzyma ją jako kod barwy w konfiguracji, więc dokument zmienia się
     * razem ze sklepem, a nie zostaje przy barwie wpisanej w dniu instalacji.
     */
    private function brandColour(): string
    {
        $marka = (string) Configuration::get('QUINCE_PRIMARY');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $marka) === 1 ? $marka : '#ffcb04';
    }

    /** @param array<string,mixed> $oferta */
    private function money(float $kwota, array $oferta): string
    {
        $waluta = new \Currency((int) $oferta['id_currency']);
        $locale = Context::getContext()->getCurrentLocale();

        if ($locale !== null && $waluta->iso_code) {
            return $locale->formatPrice($kwota, (string) $waluta->iso_code);
        }

        // Bez ustawień regionalnych (składanie z konsoli albo z zadania w tle)
        // zostaje sam zapis liczby, więc dokładamy kod waluty. Kwota bez waluty
        // w ofercie handlowej jest gorsza niż brzydka.
        return number_format($kwota, 2, ',', ' ')
            . ($waluta->iso_code ? ' ' . $waluta->iso_code : '');
    }

    private function date(string $data): string
    {
        return date('d.m.Y', strtotime($data) ?: time());
    }

    /**
     * Napis dokumentu w języku KLIENTA, nie panelu.
     *
     * Oferta wychodzi do kupującego, więc język bierzemy z niej samej. Napisy
     * z panelu jechałyby po polsku do niemieckiego klienta tylko dlatego, że
     * po polsku pracuje sprzedawca.
     */
    private function t(string $napis, int $idLang): string
    {
        return Context::getContext()->getTranslator()->trans(
            $napis,
            [],
            'Modules.Quinceoffers.Pdf',
            \Language::getLocaleById($idLang) ?: null
        );
    }

    private function e(string $tekst): string
    {
        return htmlspecialchars($tekst, ENT_QUOTES, 'UTF-8');
    }
}
