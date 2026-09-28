<?php

namespace App\Services;

class CoverLookup
{
    public function __construct(
        private BookIsbnLookup $isbnLookup,
        private GoogleBooksLookup $googleBooks,
        private NdlBookLookup $ndlLookup,
        private BookCoverResolver $coverResolver,
    ) {}

    public function resolve(string $isbn, string $title = ''): ?string
    {
        $isbn = preg_replace('/\D/', '', $isbn) ?? '';
        $title = trim($title);
        $isPlaceholder = str_starts_with($isbn, '9789999');
        $hasRealIsbn = strlen($isbn) === 13 && ! $isPlaceholder;

        if ($hasRealIsbn) {
            $found = $this->isbnLookup->lookup($isbn);

            if (($found['cover'] ?? null) !== null) {
                return $found['cover'];
            }

            if (trim((string) ($found['title'] ?? '')) !== '') {
                $title = trim((string) $found['title']);
            }
        }

        if ($title === '') {
            return $hasRealIsbn ? $this->coverResolver->resolveByIsbn($isbn) : null;
        }

        $byTitle = $this->ndlLookup->lookupByTitle($title)
            ?? $this->googleBooks->lookupByTitle($title);

        if ($byTitle !== null) {
            $fromRealIsbn = $this->isbnLookup->lookup($byTitle['isbn'])['cover'] ?? null;

            if ($fromRealIsbn) {
                return $fromRealIsbn;
            }
        }

        return $this->googleBooks->lookupCoverByTitle($title);
    }
}
