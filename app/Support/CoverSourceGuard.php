<?php

namespace App\Support;

class CoverSourceGuard
{
    public static function isAllowedUrl(string $url): bool
    {
        if (! self::isSafeToFetch($url)) {
            return false;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        $allowed = [
            'cover.openbd.jp',
            'img.hanmoto.com',
            'covers.openlibrary.org',
            'books.google.com',
        ];

        if (in_array($host, $allowed, true)) {
            return true;
        }

        return str_ends_with($host, '.google.com')
            || str_ends_with($host, '.gstatic.com')
            || str_ends_with($host, '.googleusercontent.com');
    }

    /**
     * 管理者が貼った表紙 URL を保存してよいか。社内向けホストは拒否する。
     */
    public static function isSafeToFetch(string $url): bool
    {
        if (preg_match('#^https?://\S+#i', $url) !== 1) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        return ! self::isPrivateHost($host);
    }

    private static function isPrivateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }
}
