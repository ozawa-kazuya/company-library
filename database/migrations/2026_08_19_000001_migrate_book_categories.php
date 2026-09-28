<?php

use App\Models\Book;
use App\Services\BookCategoryGuesser;
use App\Support\BookCategories;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $guesser = app(BookCategoryGuesser::class);

        Book::query()->each(function (Book $book) use ($guesser): void {
            $normalized = BookCategories::normalize($book->category);

            if ($normalized !== null) {
                if ($book->category !== $normalized) {
                    $book->update(['category' => $normalized]);
                }

                return;
            }

            $book->update(['category' => $guesser->guess($book->title ?? '')]);
        });
    }

    public function down(): void
    {
        $fallbackMap = [
            'フロントエンド・Web制作' => 'デザイン',
            'ビジネス・一般' => 'ビジネス',
        ];

        Book::query()->each(function (Book $book) use ($fallbackMap): void {
            if (isset($fallbackMap[$book->category])) {
                $book->update(['category' => $fallbackMap[$book->category]]);

                return;
            }

            if (! in_array($book->category, ['技術書', 'デザイン', 'ビジネス'], true)) {
                $book->update(['category' => '技術書']);
            }
        });
    }
};
