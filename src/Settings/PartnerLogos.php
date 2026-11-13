<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

declare(strict_types=1);

namespace Quince\Offers\Settings;

use Configuration;
use ImageManager;
use Shop;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Logotypy partnerów, dobierane po domenie adresu e-mail.
 *
 * Na ofercie dla stałego partnera stoi jego znak firmowy. Decyduje domena
 * adresu, który klient sam podał.
 *
 * Mapa domen jest osobna dla każdego sklepu: ten sam partner bywa obsługiwany
 * przez jedną spółkę z grupy, a nie przez wszystkie.
 */
final class PartnerLogos
{
    private const KEY = 'QUINCE_OFFERS_DOMAIN_LOGOS';

    /** Ścieżka względem katalogu obrazów sklepu. */
    private const SUBDIR = 'quince/offers/';

    /** Typy, które przyjmujemy, i rozszerzenie zapisywanego pliku. */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** Logotyp na dokument nie potrzebuje więcej. */
    private const MAX_BYTES = 1048576;

    /**
     * Mapa domena => nazwa pliku.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        $json = Configuration::get(self::KEY);

        if (!is_string($json) || $json === '') {
            return [];
        }

        $mapa = json_decode($json, true);

        return is_array($mapa) ? array_map('strval', $mapa) : [];
    }

    /**
     * Lista dla panelu: domena, plik, adres i informacja, czy plik istnieje.
     *
     * @return array<int,array{domain:string,file:string,url:string,exists:bool}>
     */
    public function rows(): array
    {
        $out = [];

        foreach ($this->all() as $domena => $plik) {
            $out[] = [
                'domain' => $domena,
                'file' => $plik,
                'url' => $this->url($plik),
                'exists' => is_file($this->dir() . $plik),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['domain'], $b['domain']));

        return $out;
    }

    /** Czy adres należy do partnera z listy. */
    public function isPartner(string $email): bool
    {
        return $this->fileFor($email) !== '';
    }

    /** Ścieżka do logotypu dla adresu albo pusty napis. */
    public function fileFor(string $email): string
    {
        $domena = $this->domain($email);

        if ($domena === '') {
            return '';
        }

        $plik = $this->all()[$domena] ?? '';
        $sciezka = $plik === '' ? '' : $this->dir() . $plik;

        return $sciezka !== '' && is_file($sciezka) ? $sciezka : '';
    }

    /**
     * Dołożenie albo podmiana logotypu.
     *
     * @return bool czy zapis się udał
     */
    public function save(string $domena, ?UploadedFile $plik): bool
    {
        $domena = $this->normalize($domena);

        if ($domena === '') {
            return false;
        }

        $mapa = $this->all();
        $nazwa = $this->accept($plik, $domena);

        if ($nazwa === null) {
            return false;
        }

        // Poprzedni plik tej domeny odchodzi: podmiana logotypu nie ma zostawiać
        // po sobie osieroconych obrazów, których nikt już nie odnajdzie.
        $stary = $mapa[$domena] ?? '';

        if ($stary !== '' && $stary !== $nazwa) {
            $this->removeFile($stary);
        }

        $mapa[$domena] = $nazwa;
        $this->store($mapa);

        return true;
    }

    public function remove(string $domena): void
    {
        $domena = $this->normalize($domena);
        $mapa = $this->all();

        if (!isset($mapa[$domena])) {
            return;
        }

        $this->removeFile($mapa[$domena]);
        unset($mapa[$domena]);
        $this->store($mapa);
    }

    /** Sprzątanie przy odinstalowaniu modułu. */
    public function purge(): void
    {
        foreach ($this->all() as $plik) {
            $this->removeFile($plik);
        }

        Configuration::deleteByName(self::KEY);
    }

    public function dir(): string
    {
        return _PS_IMG_DIR_ . self::SUBDIR;
    }

    public function url(string $plik): string
    {
        return $plik === '' ? '' : _PS_IMG_ . self::SUBDIR . $plik;
    }

    /**
     * Sama domena z adresu, bez wielkości liter i bez `www`.
     *
     * Porównanie po samej nazwie hosta, bo to ona identyfikuje firmę; adres
     * `jan@WWW.Firma.PL` ma trafić na ten sam logotyp co `anna@firma.pl`.
     */
    private function domain(string $email): string
    {
        $czesc = strrchr($email, '@');

        return $czesc === false ? '' : $this->normalize(substr($czesc, 1));
    }

    private function normalize(string $domena): string
    {
        $domena = strtolower(trim($domena));
        $domena = preg_replace('#^https?://#', '', $domena) ?? '';
        $domena = preg_replace('#^www\.#', '', $domena) ?? '';
        $domena = rtrim($domena, '/');

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domena) === 1 ? $domena : '';
    }

    /**
     * Przyjęcie wgranego pliku.
     *
     * Sprawdzamy sam plik, nie jego nazwę: rozszerzenie da się dopisać do
     * czegokolwiek, a obraz przepisujemy piksel po pikselu, więc wszystko, co
     * ktoś doczepił do niego poza samym obrazem, ginie po drodze.
     */
    private function accept(?UploadedFile $plik, string $domena): ?string
    {
        if ($plik === null || !$plik->isValid() || $plik->getSize() > self::MAX_BYTES) {
            return null;
        }

        $rozszerzenie = self::EXTENSIONS[$plik->getMimeType() ?? ''] ?? null;

        if ($rozszerzenie === null || !ImageManager::isRealImage($plik->getPathname())) {
            return null;
        }

        if (!is_dir($this->dir()) && !mkdir($this->dir(), 0755, true)) {
            return null;
        }

        // Nazwa z niepowtarzalnym członem: przeglądarka trzyma poprzedni plik
        // w pamięci podręcznej i bez zmiany nazwy pokazywałaby go po podmianie.
        $nazwa = str_replace('.', '-', $domena) . '-' . uniqid('', false) . '.' . $rozszerzenie;

        if (!ImageManager::resize($plik->getPathname(), $this->dir() . $nazwa, null, null, $rozszerzenie)) {
            return null;
        }

        return $nazwa;
    }

    private function removeFile(string $plik): void
    {
        $sciezka = $this->dir() . basename($plik);

        if ($plik !== '' && is_file($sciezka)) {
            @unlink($sciezka);
        }
    }

    /**
     * Zapis mapy do każdego sklepu z bieżącego kontekstu.
     *
     * `true` przy zapisie znaczy „to jest treść, nie napis do wyświetlenia".
     * Bez tego PrestaShop zamienia cudzysłowy w JSON-ie na encje i mapa wraca
     * z odczytu jako śmieci.
     *
     * @param array<string,string> $mapa
     */
    private function store(array $mapa): void
    {
        $json = json_encode($mapa);

        foreach (Shop::getContextListShopID() as $idShop) {
            Configuration::updateValue(self::KEY, $json, true, null, (int) $idShop);
        }

        if (Shop::getContext() === Shop::CONTEXT_ALL) {
            Configuration::updateGlobalValue(self::KEY, $json, true);
        }
    }
}
