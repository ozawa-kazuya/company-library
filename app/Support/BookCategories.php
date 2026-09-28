<?php

namespace App\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class BookCategories
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return config('book_categories.all', []);
    }

    public static function default(): string
    {
        return config('book_categories.default', 'ソフトウェア開発');
    }

    public static function isValid(?string $category): bool
    {
        return in_array($category, self::all(), true);
    }

    /**
     * @return list<ValidationRule|string>
     */
    public static function validationRules(bool $required = true): array
    {
        $rules = ['string', Rule::in(self::all())];

        if ($required) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    /**
     * 旧カテゴリ（技術書 / デザイン / ビジネス）を新カテゴリへ変換する。
     */
    public static function normalize(?string $category): ?string
    {
        $category = trim((string) $category);

        if ($category === '') {
            return null;
        }

        if (self::isValid($category)) {
            return $category;
        }

        return match ($category) {
            'デザイン' => 'フロントエンド・Web制作',
            'ビジネス' => 'ビジネス・一般',
            default => null,
        };
    }
}
