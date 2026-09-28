<?php

namespace Tests\Unit;

use App\Services\BookIsbnLookup;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookIsbnLookupTest extends TestCase
{
    public function test_falls_back_to_google_books_when_openbd_has_no_record(): void
    {
        Http::fake([
            'api.openbd.jp/*' => Http::response([null]),
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'img.hanmoto.com/*' => Http::response('', 404),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'The Pragmatic Programmer',
                        'imageLinks' => [
                            'thumbnail' => 'https://books.google.com/books/content?id=RealId&img=1',
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(BookIsbnLookup::class)->lookup('9780135957059');

        $this->assertSame('The Pragmatic Programmer', $result['title']);
        $this->assertSame('https://books.google.com/books/content?id=RealId&img=1', $result['cover']);
    }

    public function test_uses_google_cover_when_openbd_has_title_but_no_cover(): void
    {
        Http::fake([
            'api.openbd.jp/*' => Http::response([[
                'summary' => [
                    'isbn' => '9784798168494',
                    'title' => '独習PHP',
                    'cover' => '',
                ],
            ]]),
            'img.hanmoto.com/*' => Http::response('', 404),
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => '独習PHP',
                        'imageLinks' => [
                            'thumbnail' => 'https://books.google.com/books/content?id=PhpCover&img=1',
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(BookIsbnLookup::class)->lookup('9784798168494');

        $this->assertSame('独習PHP', $result['title']);
        $this->assertSame('https://books.google.com/books/content?id=PhpCover&img=1', $result['cover']);
    }

    public function test_falls_back_to_ndl_when_openbd_and_google_miss_title(): void
    {
        Http::fake([
            'api.openbd.jp/*' => Http::response([null]),
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'img.hanmoto.com/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
            'www.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429),
            'ndlsearch.ndl.go.jp/*' => Http::response(
                '<?xml version="1.0"?><rss><channel><item>'
                .'<dc:title>これからはじめるReact実践入門 : コンポーネントの基本からNext.jsによるアプリ開発まで</dc:title>'
                .'<dc:identifier xsi:type="dcndl:ISBN">978-4-8156-3594-7</dc:identifier>'
                .'</item></channel></rss>'
            ),
        ]);

        $result = app(BookIsbnLookup::class)->lookup('9784815635947');

        $this->assertSame(
            'これからはじめるReact実践入門 : コンポーネントの基本からNext.jsによるアプリ開発まで',
            $result['title'],
        );
        $this->assertSame('https://img.hanmoto.com/bd/img/9784815635947.jpg', $result['cover']);
    }
}
