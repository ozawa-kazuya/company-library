<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NdlBookLookup
{
    private const OPENSEARCH_URL = 'https://ndlsearch.ndl.go.jp/api/opensearch';

    /**
     * @return array{isbn: string, title: string}|null
     */
    public function lookupByIsbn(string $isbn): ?array
    {
        $isbn = preg_replace('/\D/', '', $isbn) ?? '';

        if (strlen($isbn) !== 13) {
            return null;
        }

        $xml = $this->fetchOpenSearch([
            'isbn' => $isbn,
            'cnt' => 5,
        ]);

        if ($xml === null) {
            return null;
        }

        foreach ($this->items($xml) as $item) {
            $title = $this->extractTitle($item);

            if ($title === '') {
                continue;
            }

            $foundIsbn = $this->extractIsbn($item) ?? $isbn;

            return [
                'isbn' => $foundIsbn,
                'title' => $title,
            ];
        }

        return null;
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

        $xml = $this->fetchOpenSearch([
            'title' => $title,
            'books' => 'true',
            'cnt' => 10,
        ]);

        if ($xml === null) {
            return null;
        }

        $normalizedTitle = preg_replace('/\s+/u', '', $title);
        $best = null;

        foreach ($this->items($xml) as $item) {
            $isbn = $this->extractIsbn($item);

            if ($isbn === null) {
                continue;
            }

            $foundTitle = $this->extractTitle($item) ?: $title;
            $foundNormalized = preg_replace('/\s+/u', '', $foundTitle);

            $score = 1;
            if ($foundNormalized === $normalizedTitle) {
                $score = 10;
            } elseif (str_contains($foundNormalized, $normalizedTitle) || str_contains($normalizedTitle, $foundNormalized)) {
                $score = 5;
            }

            $candidate = [
                'isbn' => $isbn,
                'title' => $foundTitle,
                'score' => $score,
            ];

            if ($best === null || $candidate['score'] > $best['score']) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            return null;
        }

        unset($best['score']);

        return $best;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function fetchOpenSearch(array $query): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'CompanyLibrary/1.0'])
                ->get(self::OPENSEARCH_URL, $query);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $xml = $response->body();

        return $xml === '' ? null : $xml;
    }

    /**
     * @return list<string>
     */
    private function items(string $xml): array
    {
        $items = preg_split('/<item>/', $xml) ?: [];
        array_shift($items);

        return array_values(array_filter($items, fn ($item) => is_string($item) && $item !== ''));
    }

    private function extractTitle(string $item): string
    {
        foreach (['/<dc:title>([^<]+)/', '/<title>([^<]+)/'] as $pattern) {
            if (preg_match($pattern, $item, $match) === 1) {
                $title = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($title !== '') {
                    return $title;
                }
            }
        }

        return '';
    }

    private function extractIsbn(string $item): ?string
    {
        if (preg_match('/xsi:type="dcndl:ISBN">([^<]+)/', $item, $match) !== 1) {
            return null;
        }

        return $this->toIsbn13($match[1]);
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
