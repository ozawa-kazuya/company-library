<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BookCoverResolver
{
    /**
     * OpenBD レスポンスから表紙 URL を解決する（外部フォールバックはしない）。
     */
    public function resolveFromOpenBd(array $payloadItem): ?string
    {
        foreach ($this->openBdCoverUrls($payloadItem) as $url) {
            if (! $this->isUnavailableCoverUrl($url)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * OpenBD → 版元ドットコム → Open Library の順で表紙を探す。
     *
     * @param  array<string, mixed>|null  $openBdItem
     */
    public function resolveByIsbn(string $isbn, ?array $openBdItem = null): ?string
    {
        $isbn = preg_replace('/\D/', '', $isbn) ?? '';

        if (strlen($isbn) !== 13) {
            return null;
        }

        if ($openBdItem !== null) {
            $fromOpenBd = $this->resolveFromOpenBd($openBdItem);

            if ($fromOpenBd !== null) {
                return $fromOpenBd;
            }
        }

        return $this->resolveFirstAvailable($this->candidateUrls($isbn));
    }

    public function resolveHanmoto(string $isbn): ?string
    {
        $isbn = preg_replace('/\D/', '', $isbn) ?? '';

        if (strlen($isbn) !== 13) {
            return null;
        }

        $url = 'https://img.hanmoto.com/bd/img/'.$isbn.'.jpg';

        return $this->isReachableImage($url) ? $url : null;
    }

    /**
     * @param  array<int, string>  $candidateUrls
     */
    public function resolveFirstAvailable(array $candidateUrls): ?string
    {
        foreach ($candidateUrls as $url) {
            $normalized = trim((string) $url);

            if ($normalized === '' || $this->isUnavailableCoverUrl($normalized)) {
                continue;
            }

            if ($this->isReachableImage($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    public function isUnavailableCoverUrl(string $url): bool
    {
        return str_contains($url, 'books.google.com/books/content?id=ISBN:');
    }

    /**
     * @return array<int, string>
     */
    public function candidateUrls(string $isbn): array
    {
        $isbn = preg_replace('/\D/', '', $isbn) ?? '';

        if (strlen($isbn) !== 13) {
            return [];
        }

        return [
            'https://cover.openbd.jp/'.$isbn.'.jpg',
            'https://img.hanmoto.com/bd/img/'.$isbn.'.jpg',
            'https://covers.openlibrary.org/b/isbn/'.$isbn.'-L.jpg?default=false',
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadItem
     * @return array<int, string>
     */
    private function openBdCoverUrls(array $payloadItem): array
    {
        $urls = [];
        $summaryCover = trim((string) ($payloadItem['summary']['cover'] ?? ''));

        if ($summaryCover !== '') {
            $urls[] = $summaryCover;
        }

        foreach ($this->extractResourceLinks($payloadItem['onix']['CollateralDetail'] ?? []) as $link) {
            $url = $this->normalizeResourceLink($link);

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param  array<string, mixed>  $collateralDetail
     * @return array<int, string>
     */
    private function extractResourceLinks(array $collateralDetail): array
    {
        $resources = $collateralDetail['SupportingResource'] ?? null;

        if ($resources === null) {
            return [];
        }

        if (isset($resources['ResourceLink'])) {
            $resources = [$resources];
        }

        $links = [];

        foreach ($resources as $resource) {
            if (! is_array($resource)) {
                continue;
            }

            $link = $resource['ResourceLink'] ?? null;

            if (is_string($link) && $link !== '') {
                $links[] = $link;
            } elseif (is_array($link)) {
                $content = $link['content'] ?? null;

                if (is_string($content) && $content !== '') {
                    $links[] = $content;
                }
            }
        }

        return $links;
    }

    private function normalizeResourceLink(string $link): ?string
    {
        $link = trim($link);

        if ($link === '') {
            return null;
        }

        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            return $link;
        }

        return 'https://cover.openbd.jp'.(str_starts_with($link, '/') ? '' : '/').$link;
    }

    private function isReachableImage(string $url): bool
    {
        $headers = ['User-Agent' => 'CompanyLibrary/1.0'];

        try {
            $head = Http::timeout(3)->withHeaders($headers)->head($url);

            if ($head->successful() && $this->isImageContentType($head) && $this->declaredSize($head) >= 1000) {
                return true;
            }
        } catch (\Throwable) {
            // HEAD 非対応の CDN は GET で確認する
        }

        return $this->getLooksLikeRealImage($url, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function getLooksLikeRealImage(string $url, array $headers): bool
    {
        try {
            $response = Http::timeout(5)->withHeaders($headers)->get($url);
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful() || ! $this->isImageContentType($response)) {
            return false;
        }

        $size = max($this->declaredSize($response), strlen((string) $response->body()));

        return $size >= 1000;
    }

    private function isImageContentType(Response $response): bool
    {
        return str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/');
    }

    private function declaredSize(Response $response): int
    {
        return (int) $response->header('Content-Length');
    }
}
