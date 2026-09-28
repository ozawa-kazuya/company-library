<?php

namespace App\Support;

use App\Support\LoginFieldRules;

final class InitialPasswordGenerator
{
    private const CHARS = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function make(): string
    {
        $length = min(8, LoginFieldRules::PASSWORD_MAX);
        $max = strlen(self::CHARS) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= self::CHARS[random_int(0, $max)];
        }

        return $password;
    }
}
