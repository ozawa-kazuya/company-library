<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleBooksLookup
{
    /**
     * @return array{title: string, cover: ?string}|null
     */
    public function lookupByIsbn(string $isbn): ?array
    {
        $isbn = preg_replace('/\D/', '', $isbn) ?? '';

        if (strlen($isbn) !== 13) {
            return null;
        }

        $volume = $this->fetchFirstVolume('isbn:'.$isbn);

        if ($volume === null) {
            return null;
        }

        $title = trim((string) ($volume['volumeInfo']['title'] ?? ''));

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'cover' => $this->extractCoverUrl($volume),
        ];
    }

    /**
     * @return array{isbn: string, title: string}|null
     */
    public function lookupByTitle(string $title): ?array
    {
        $title = trim($title);

        if ($title === '') {
            return null;
        }

        $volume = $this->fetchFirstVolume($title, 5);

        if ($volume === null) {
            return null;
        }

        $isbn = $this->extractIsbn13($volume);

        if ($isbn === null) {
            return null;
        }

        $foundTitle = trim((string) ($volume['volumeInfo']['title'] ?? $title));

        return [
            'isbn' => $isbn,
            'title' => $foundTitle !== '' ? $foundTitle : $title,
        ];
    }

    public function lookupCoverByTitle(string $title): ?string
    {
        $title = trim($title);

        if ($title === '') {
            return null;
        }

        $volume = $this->fetchFirstVolume($title, 5);

        if ($volume === null) {
            return null;
        }

        $cover = $this->extractCoverUrl($volume);

        if ($cover !== null) {
            return $cover;
        }

        $isbn = $this->extractIsbn13($volume);

        if ($isbn === null) {
            return null;
        }

        return $this->lookupByIsbn($isbn)['cover'] ?? null;
    }

    public function resolveCover(string $isbn): ?string
    {
        return $this->lookupByIsbn($isbn)['cover'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFirstVolume(string $search, int $maxResults = 1): ?array
    {
        try {
            $query = [
                'q' => $search,
                'maxResults' => $maxResults,
                'printType' => 'books',
            ];
            $apiKey = trim((string) config('library.google_books_api_key', ''));

            if ($apiKey !== '') {
                $query['key'] = $apiKey;
            }

            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'CompanyLibrary/1.0'])
                ->get('https://www.googleapis.com/books/v1/volumes', $query);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $item = $response->json('items.0');

        return is_array($item) ? $item : null;
    }

    /**
     * @param  array<string, mixed>  $volume
     */
    private function extractCoverUrl(array $volume): ?string
    {
        $links = $volume['volumeInfo']['imageLinks'] ?? [];

        if (! is_array($links)) {
            return null;
        }

        foreach (['thumbnail', 'smallThumbnail'] as $key) {
            $normalized = $this->normalizeCoverUrl((string) ($links[$key] ?? ''));

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeCoverUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $url = str_replace('http://', 'https://', $url);

        if (str_contains($url, 'books.google.com/books/content?id=ISBN:')) {
            return null;
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $volume
     */
    private function extractIsbn13(array $volume): ?string
    {
        $identifiers = $volume['volumeInfo']['industryIdentifiers'] ?? [];

        if (! is_array($identifiers)) {
            return null;
        }

        $isbn10 = null;

        foreach ($identifiers as $identifier) {
            if (! is_array($identifier)) {
                continue;
            }

            $type = (string) ($identifier['type'] ?? '');
            $value = (string) ($identifier['identifier'] ?? '');

            if ($type === 'ISBN_13') {
                $isbn = preg_replace('/\D/', '', $value) ?? '';

                if (strlen($isbn) === 13) {
                    return $isbn;
                }
            }

            if ($type === 'ISBN_10') {
                $isbn10 = $value;
            }
        }

        return $isbn10 !== null ? $this->toIsbn13($isbn10) : null;
    }

    private function toIsbn13(string $raw): ?string
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', $raw) ?? '';

        if (strlen($isbn) === 13) {
            return $isbn;
        }

        if (strlen($isbn) !== 10) {
            return null;
        }

        $core = '978'.substr($isbn, 0, 9);
        $sum = 0;

        foreach (str_split($core) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        $check = (10 - ($sum % 10)) % 10;

        return $core.$check;
    }
}
