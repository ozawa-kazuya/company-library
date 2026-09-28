<?php

namespace App\Support;

use Closure;

class BookCoverRules
{
    public static function isLocalPath(?string $value): bool
    {
        return is_string($value)
            && preg_match('/^\/covers\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * @return list<string|Closure>
     */
    public static function validationRules(): array
    {
        return [
            'nullable',
            'string',
            'max:500',
            function (string $attribute, mixed $value, Closure $fail): void {
                $cover = trim((string) $value);

                if ($cover === '') {
                    return;
                }

                if (self::isLocalPath($cover)) {
                    return;
                }

                if (preg_match('#^https?://\S+#i', $cover) === 1) {
                    if (! CoverSourceGuard::isSafeToFetch($cover)) {
                        $fail('表紙のURLが不正です。');

                        return;
                    }

                    return;
                }

                $fail('表紙は画像ファイルまたは画像のURLで指定してください。');
            },
        ];
    }
}
