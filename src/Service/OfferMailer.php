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
use Mail;
use Quince\Offers\Settings\OfferSettings;

/**
 * Wysyłka oferty do klienta.
 *
 * Plik PDF dochodzi tu jako załącznik w kolejnym kroku; na razie mail niesie
 * numer, datę ważności i zdanie wpisane przez sklep w ustawieniach — to samo,
 * które potem stanie nad załącznikiem.
 *
 * Kopie ukryte idą na adresy z ustawień. Handlowiec ma wiedzieć o zapytaniu
 * w tej samej chwili co klient, bez zaglądania do panelu.
 */
final class OfferMailer
{
    public function __construct(private readonly OfferSettings $settings)
    {
    }

    /**
     * @param array<string,mixed> $oferta
     */
    public function send(array $oferta): bool
    {
        $kontekst = Context::getContext();
        $idLang = (int) $oferta['id_lang'];
        $nazwaSklepu = (string) Configuration::get('PS_SHOP_NAME');

        $zmienne = [
            '{shop_name}' => $nazwaSklepu,
            '{shop_url}' => $kontekst->shop->getBaseURL(true),
            '{customer_name}' => (string) $oferta['customer_name'],
            '{offer_number}' => (string) $oferta['number'],
            '{valid_until}' => (string) $oferta['valid_until'],
            '{message}' => $this->settings->text('EMAIL_BODY', $idLang),
            '{items}' => $this->itemsHtml($oferta['items'] ?? []),
        ];

        $zalacznik = $this->attachment($oferta);
        $temat = $this->subject($oferta, $zmienne, $nazwaSklepu);
        $odpowiedz = $this->replyTo($idLang);

        $wyslane = Mail::Send(
            $idLang,
            'quince_offer',
            $temat,
            $zmienne,
            (string) $oferta['email'],
            (string) $oferta['customer_name'],
            // Nadawcy nie podajemy: PrestaShop bierze wtedy adres sklepu, czyli
            // ten, pod który skonfigurowana jest poczta wychodząca. Wstawienie
            // tu adresu z innej domeny wygląda w panelu dobrze, a u odbiorcy
            // kończy się w spamie, bo serwer sklepu nie ma prawa wysyłać
            // w imieniu tamtej domeny.
            null,
            null,
            $zalacznik,
            null,
            _PS_MODULE_DIR_ . 'quince_offers/mails/',
            false,
            (int) $oferta['id_shop'],
            null,
            // Adres zwrotny to co innego niż nadawca: nie musi należeć do domeny
            // sklepu i nie psuje doręczalności. Odpowiedź klienta trafia wtedy
            // do osoby, która ofertę wystawiła, a nie na ogólną skrzynkę.
            $odpowiedz['email'],
            $odpowiedz['name']
        );

        $this->sendCopies($oferta, $zmienne, $nazwaSklepu);

        return (bool) $wyslane;
    }

    /**
     * Temat wiadomości.
     *
     * Sklep wpisuje go w ustawieniach, na każdy język osobno, i może wstawić
     * w nim te same znaczniki co w treści. Puste pole znaczy „ułóż sam" —
     * nazwa sklepu i numer, bo to dwie rzeczy, po których klient rozpozna
     * wiadomość na liście w skrzynce.
     *
     * @param array<string,mixed>  $oferta
     * @param array<string,string> $zmienne
     */
    private function subject(array $oferta, array $zmienne, string $nazwaSklepu): string
    {
        $wzor = $this->settings->text('EMAIL_SUBJECT', (int) $oferta['id_lang']);

        if (trim($wzor) === '') {
            return $nazwaSklepu . ' — ' . $oferta['number'];
        }

        return strtr($wzor, $zmienne);
    }

    /**
     * @param array<string,mixed>  $oferta
     * @param array<string,string> $zmienne
     */
    private function sendCopies(array $oferta, array $zmienne, string $nazwaSklepu): void
    {
        $idLang = (int) $oferta['id_lang'];

        foreach ($this->copyContacts($idLang) as $adres) {
            Mail::Send(
                (int) $oferta['id_lang'],
                'quince_offer',
                // Kopia dla handlowca niesie w temacie adres klienta: inaczej
                // dziesięć kopii w skrzynce różni się tylko numerem.
                $this->subject($oferta, $zmienne, $nazwaSklepu) . ' (' . $oferta['email'] . ')',
                $zmienne,
                $adres,
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . 'quince_offers/mails/',
                false,
                (int) $oferta['id_shop']
            );
        }
    }

    /**
     * Adresy, na które idzie kopia oferty.
     *
     * Sklep wskazuje w ustawieniach kontakty, a nie wpisuje adresów: adres
     * handlowca i tak żyje w Sklep → Kontakty razem z nazwiskiem, a wpisany
     * drugi raz w module zaczyna żyć własnym życiem i po zmianie w kontaktach
     * nikt o nim nie pamięta.
     *
     * @return array<int,string>
     */
    private function copyContacts(int $idLang): array
    {
        $wybrane = array_filter(array_map('intval', explode(',', $this->settings->get('COPY_CONTACTS'))));
        $adresy = [];

        foreach ($wybrane as $idContact) {
            $kontakt = new \Contact($idContact, $idLang);

            if ($kontakt->id && \Validate::isEmail($kontakt->email)) {
                $adresy[] = (string) $kontakt->email;
            }
        }

        // Ten sam adres wpisany przy dwóch kontaktach nie ma dostać dwóch kopii.
        return array_values(array_unique($adresy));
    }

    /**
     * Adres, na który wraca odpowiedź klienta.
     *
     * Sklep wskazuje go w ustawieniach spośród własnych kontaktów. Nadawcą
     * zostaje adres sklepu — ten, pod który skonfigurowana jest poczta
     * wychodząca — bo tylko z niego wolno wysyłać. Adres zwrotny takiego
     * ograniczenia nie ma, więc handlowiec dostaje odpowiedź do siebie,
     * a wiadomość dalej wychodzi zgodnie z konfiguracją serwera.
     *
     * @return array{email:string|null,name:string}
     */
    private function replyTo(int $idLang): array
    {
        $idContact = (int) $this->settings->get('REPLY_CONTACT');

        if ($idContact > 0) {
            $kontakt = new \Contact($idContact, $idLang);

            if ($kontakt->id && \Validate::isEmail($kontakt->email)) {
                return [
                    'email' => (string) $kontakt->email,
                    'name' => (string) $kontakt->name,
                ];
            }
        }

        // Bez wyboru i przy kontakcie, którego już nie ma, odpowiedź wraca tam,
        // skąd wiadomość wyszła. Pusty adres znaczy dla PrestaShopa „nie
        // ustawiaj nagłówka".
        return ['email' => null, 'name' => ''];
    }

    /**
     * Gotowy plik jako załącznik.
     *
     * Wysyłamy treść pliku, a nie odnośnik do niego: oferta ma zostać w skrzynce
     * klienta także wtedy, gdy sklep zmieni adres albo plik zostanie posprzątany.
     *
     * @param array<string,mixed> $oferta
     *
     * @return array{content:string,name:string,mime:string}|null
     */
    private function attachment(array $oferta): ?array
    {
        $plik = (string) ($oferta['pdf_file'] ?? '');

        if ($plik === '') {
            return null;
        }

        $sciezka = PdfGenerator::directory() . basename($plik);

        if (!is_file($sciezka)) {
            return null;
        }

        return [
            'content' => (string) file_get_contents($sciezka),
            'name' => str_replace('/', '-', (string) $oferta['number']) . '.pdf',
            'mime' => 'application/pdf',
        ];
    }

    /**
     * Pozycje jako wiersze tabeli w mailu.
     *
     * Skład idzie tutaj, a nie w szablonie, bo szablon maila nie ma pętli —
     * PrestaShop podmienia w nim wyłącznie zmienne.
     *
     * @param array<int,array<string,mixed>> $pozycje
     */
    private function itemsHtml(array $pozycje): string
    {
        $wiersze = '';
        $waluta = Context::getContext()->currency;

        foreach ($pozycje as $pozycja) {
            $wiersze .= '<tr>'
                . '<td style="padding:6px 0">' . htmlspecialchars((string) $pozycja['name'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:6px 0;text-align:right">' . (int) $pozycja['quantity'] . '</td>'
                . '<td style="padding:6px 0;text-align:right">'
                . Context::getContext()->getCurrentLocale()->formatPrice((float) $pozycja['price_net'], $waluta->iso_code)
                . '</td>'
                . '</tr>';
        }

        return $wiersze;
    }
}
