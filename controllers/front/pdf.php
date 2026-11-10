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

use Quince\Offers\Service\PdfGenerator;
use Quince\Offers\Settings\OfferStore;

/**
 * Pobranie pliku z ofertą.
 *
 * Plik leży poza katalogiem serwowanym przez serwer, więc to jedyna droga do
 * niego. Klucz jest losowy i przypisany do jednej oferty; adres z kluczem trafia
 * do klienta w mailu i w szufladzie.
 *
 * Oferta wystawiona zalogowanemu klientowi wymaga dodatkowo, żeby pobierał ją
 * ten sam klient. Oferty gościa broni sam klucz — konta, do którego można by ją
 * przypisać, po prostu nie ma.
 */
class Quince_OffersPdfModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        $token = (string) Tools::getValue('token');

        if ($token === '' || !preg_match('/^[0-9a-f]{32}$/', $token)) {
            $this->nieZnaleziono();
        }

        $oferta = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . OfferStore::TABLE . '`'
            . ' WHERE pdf_token = "' . pSQL($token) . '"'
        );

        if (!$oferta || (string) $oferta['pdf_file'] === '') {
            $this->nieZnaleziono();
        }

        if ((int) $oferta['id_customer'] > 0
            && (int) $this->context->customer->id !== (int) $oferta['id_customer']
        ) {
            $this->nieZnaleziono();
        }

        $sciezka = PdfGenerator::directory() . basename((string) $oferta['pdf_file']);

        if (!is_file($sciezka)) {
            $this->nieZnaleziono();
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($sciezka));
        header('Content-Disposition: attachment; filename="'
            . str_replace('/', '-', (string) $oferta['number']) . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');

        readfile($sciezka);
        exit;
    }

    private function nieZnaleziono(): void
    {
        header('HTTP/1.1 404 Not Found');
        exit;
    }
}
