<?php

namespace App\Support;

class OverdueFormatter
{
    public static function label(int $overdueSeconds): string
    {
        $seconds = max(0, $overdueSeconds);

        if ($seconds <= 0) {
            return '期限切れ';
        }

        $totalMinutes = intdiv($seconds, 60);
        $totalHours = intdiv($totalMinutes, 60);
        $days = intdiv($totalHours, 24);

        if ($days >= 1) {
            $hours = $totalHours % 24;

            return $hours > 0
                ? "{$days}日{$hours}時間超過"
                : "{$days}日超過";
        }

        if ($totalHours >= 1) {
            $minutes = $totalMinutes % 60;

            return $minutes > 0
                ? "{$totalHours}時間{$minutes}分超過"
                : "{$totalHours}時間超過";
        }

        if ($totalMinutes >= 1) {
            return "{$totalMinutes}分超過";
        }

        return max(1, $seconds).'秒超過';
    }
}
