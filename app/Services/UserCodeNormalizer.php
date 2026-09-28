<?php

namespace App\Services;

class UserCodeNormalizer
{
    /**
     * 社員番号を3桁数字（001形式）に正規化する。
     */
    public function normalize(string $input): string
    {
        $input = strtoupper(trim($input));

        if (preg_match('/^EMP-?(\d+)$/i', $input, $matches)) {
            return str_pad($matches[1], 3, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^\d+$/', $input)) {
            return str_pad($input, 3, '0', STR_PAD_LEFT);
        }

        return $input;
    }

    /**
     * ログイン・検索用に、新旧フォーマットの候補を返す。
     *
     * @return array<int, string>
     */
    public function resolveCandidates(string $input): array
    {
        $trimmed = strtoupper(trim($input));
        $normalized = $this->normalize($trimmed);
        $candidates = array_filter([$trimmed, $normalized]);

        if (preg_match('/^\d{3}$/', $normalized)) {
            $number = (int) $normalized;
            $candidates[] = 'EMP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $candidates[] = 'EMP-'.$normalized;
        }

        return array_values(array_unique($candidates));
    }
}
