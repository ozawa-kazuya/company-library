<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class ReturnLocationGuard
{
    public static function isRestricted(): bool
    {
        return (bool) config('library.return_location.restricted', false);
    }

    public static function allows(Request $request): bool
    {
        if (! self::isRestricted()) {
            return true;
        }

        $ips = self::clientIps($request);

        if ($ips === []) {
            return false;
        }

        if (config('library.return_location.allow_localhost', false)
            && array_intersect($ips, ['127.0.0.1', '::1']) !== []) {
            return true;
        }

        $networks = config('library.return_location.allowed_networks', []);

        if ($networks === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (IpUtils::checkIp($ip, $networks)) {
                return true;
            }
        }

        return false;
    }

    /**
     * デバッグ用：許可/拒否の理由
     */
    public static function reason(Request $request): string
    {
        if (! self::isRestricted()) {
            return '制限オフ';
        }

        $ip = self::primaryIp($request);

        if ($ip === null) {
            return 'IP取得不可';
        }

        if (self::allows($request)) {
            return '許可ネットワーク内';
        }

        if (str_starts_with($ip, '172.20.10.')) {
            return 'テザリング回線（拒否）';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '社内Wi-Fi以外のLAN（拒否）';
        }

        return 'モバイル回線等（拒否）';
    }

    public static function locationName(): string
    {
        return (string) config('library.return_location.name', '社内Wi-Fi');
    }

    public static function denialMessage(string $action = 'return'): string
    {
        $custom = config('library.return_location.denial_message');

        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        $locationName = self::locationName();

        return match ($action) {
            'borrow' => sprintf(
                '貸出は%s（社内ネットワーク）からのみ可能です。オフィスで借り出してください。',
                $locationName,
            ),
            default => sprintf(
                '返却は%s（社内ネットワーク）からのみ可能です。オフィスに戻ってから返却してください。',
                $locationName,
            ),
        };
    }

    /**
     * @return array{restricted: bool, allowedHere: bool, locationName: string, message: string, currentIp: ?string, reason: string, showDebug: bool, lanIpHint: ?string}
     */
    public static function status(Request $request, string $action = 'return'): array
    {
        $ip = self::primaryIp($request);

        return [
            'restricted' => self::isRestricted(),
            'allowedHere' => self::allows($request),
            'locationName' => self::locationName(),
            'message' => self::denialMessage($action),
            'currentIp' => $ip,
            'reason' => self::reason($request),
            'showDebug' => app()->environment('local')
                || (self::isRestricted() && ! self::allows($request)),
            'lanIpHint' => app()->environment('local') ? self::detectLanIp() : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function clientIps(Request $request): array
    {
        $candidates = array_merge(
            [$request->ip()],
            $request->ips(),
        );

        $ips = [];

        foreach ($candidates as $ip) {
            if (! is_string($ip) || $ip === '') {
                continue;
            }

            $normalized = self::normalizeIp($ip);

            if ($normalized !== '') {
                $ips[$normalized] = $normalized;
            }
        }

        return array_values($ips);
    }

    private static function primaryIp(Request $request): ?string
    {
        return self::clientIps($request)[0] ?? null;
    }

    private static function normalizeIp(string $ip): string
    {
        $ip = strtolower(trim($ip));

        if (str_starts_with($ip, '::ffff:')) {
            return substr($ip, 7);
        }

        return $ip;
    }

    private static function detectLanIp(): ?string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $ip = trim((string) shell_exec('ipconfig getifaddr en0 2>/dev/null'));

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $ip = trim((string) shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }
}
