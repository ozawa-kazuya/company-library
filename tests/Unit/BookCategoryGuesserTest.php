<?php

namespace Tests\Unit;

use App\Services\BookCategoryGuesser;
use Tests\TestCase;

class BookCategoryGuesserTest extends TestCase
{
    public function test_guesses_frontend_category_from_react_title(): void
    {
        $guesser = new BookCategoryGuesser();

        $this->assertSame(
            'フロントエンド・Web制作',
            $guesser->guess('改訂新版 これからはじめるReact実践入門'),
        );
        $this->assertSame(
            'PHP・Laravel',
            $guesser->guess('1週間でPHPの基礎が学べる本'),
        );
        $this->assertSame(
            'ソフトウェア開発',
            $guesser->guess('よくわからない本'),
        );
    }
}
