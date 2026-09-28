<?php

namespace Tests\Unit;

use App\Services\GoogleBooksLookup;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksLookupTest extends TestCase
{
    public function test_lookup_by_isbn_returns_title_and_https_cover(): void
    {
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => '独習PHP',
                        'imageLinks' => [
                            'thumbnail' => 'http://books.google.com/books/content?id=AbCdEf&printsec=frontcover&img=1&zoom=1',
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(GoogleBooksLookup::class)->lookupByIsbn('9784798168494');

        $this->assertSame('独習PHP', $result['title']);
        $this->assertSame(
            'https://books.google.com/books/content?id=AbCdEf&printsec=frontcover&img=1&zoom=1',
            $result['cover'],
        );
    }

    public function test_rejects_isbn_placeholder_cover(): void
    {
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => '独習PHP',
                        'imageLinks' => [
                            'thumbnail' => 'https://books.google.com/books/content?id=ISBN:9784798168494&printsec=frontcover&img=1',
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(GoogleBooksLookup::class)->lookupByIsbn('9784798168494');

        $this->assertSame('独習PHP', $result['title']);
        $this->assertNull($result['cover']);
    }

    public function test_lookup_by_title_returns_isbn13(): void
    {
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => '独習PHP 第4版',
                        'industryIdentifiers' => [
                            ['type' => 'ISBN_10', 'identifier' => '479816849X'],
                            ['type' => 'ISBN_13', 'identifier' => '978-4-7981-6849-4'],
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(GoogleBooksLookup::class)->lookupByTitle('独習PHP');

        $this->assertSame('9784798168494', $result['isbn']);
        $this->assertSame('独習PHP 第4版', $result['title']);
    }

    public function test_lookup_cover_by_title_returns_https_cover(): void
    {
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'DUO 3.0',
                        'imageLinks' => [
                            'thumbnail' => 'http://books.google.com/books/content?id=DuoId&img=1',
                        ],
                    ],
                ]],
            ]),
        ]);

        $cover = app(GoogleBooksLookup::class)->lookupCoverByTitle('DUO3.0現代英語の重要単語');

        $this->assertSame('https://books.google.com/books/content?id=DuoId&img=1', $cover);
    }
}
