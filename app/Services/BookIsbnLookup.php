<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BookIsbnLookup
{
    public function __construct(
        private BookCoverResolver $coverResolver,
        private GoogleBooksLookup $googleBooks,
        private NdlBookLookup $ndlLookup,
    ) {}

    /**
     * OpenBD → 版元ドットコム → Open Library → Google Books → 国会図書館 の順で書誌を探す。
     *
     * @return array{title: string, cover: ?string}|null
     */
    public function lookup(string $isbn): ?array
    {
        $isbn = preg_replace('/\D/', '', $isbn) ?? '';

        if (strlen($isbn) !== 13) {
            return null;
        }

        $openBd = $this->fetchOpenBd($isbn);
        $title = '';
        $cover = null;

        if ($openBd !== null) {
            $title = trim((string) ($openBd['summary']['title'] ?? ''));
        }

        $cover = $this->coverResolver->resolveByIsbn($isbn, $openBd);

        if ($title === '' || $cover === null) {
            $google = $this->googleBooks->lookupByIsbn($isbn);

            if ($google !== null) {
                if ($title === '') {
                    $title = $google['title'];
                }

                $cover ??= $google['cover'];
            }
        }

        if ($title === '') {
            $ndl = $this->ndlLookup->lookupByIsbn($isbn);

            if ($ndl !== null) {
                $title = trim($ndl['title']);
            }
        }

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'cover' => $cover,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOpenBd(string $isbn): ?array
    {
        try {
            $response = Http::timeout(5)->get("https://api.openbd.jp/v1/get?isbn={$isbn}");
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload[0]) || ! is_array($payload[0])) {
            return null;
        }

        return $payload[0];
    }
}
