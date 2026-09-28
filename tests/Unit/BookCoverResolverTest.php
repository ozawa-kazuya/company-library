<?php

namespace Tests\Unit;

use App\Services\BookCoverResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookCoverResolverTest extends TestCase
{
    public function test_uses_openbd_summary_cover_when_present(): void
    {
        Http::fake();

        $url = app(BookCoverResolver::class)->resolveFromOpenBd([
            'summary' => [
                'isbn' => '9784798168494',
                'cover' => 'https://cover.openbd.jp/9784798168494.jpg',
            ],
        ]);

        $this->assertSame('https://cover.openbd.jp/9784798168494.jpg', $url);
        Http::assertNothingSent();
    }

    public function test_falls_back_to_hanmoto_when_openbd_has_no_cover(): void
    {
        Http::fake([
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'img.hanmoto.com/*' => Http::response('', 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '20000',
            ]),
        ]);

        $url = app(BookCoverResolver::class)->resolveByIsbn('9784798168494', [
            'summary' => [
                'isbn' => '9784798168494',
                'cover' => '',
            ],
        ]);

        $this->assertSame('https://img.hanmoto.com/bd/img/9784798168494.jpg', $url);
    }

    public function test_falls_back_to_open_library_when_hanmoto_is_missing(): void
    {
        Http::fake([
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'img.hanmoto.com/*' => Http::response('', 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '176',
            ]),
            'covers.openlibrary.org/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '2000',
            ]),
        ]);

        $url = app(BookCoverResolver::class)->resolveByIsbn('9780135957059');

        $this->assertSame(
            'https://covers.openlibrary.org/b/isbn/9780135957059-L.jpg?default=false',
            $url,
        );
    }

    public function test_rejects_tiny_hanmoto_placeholder(): void
    {
        Http::fake([
            'img.hanmoto.com/*' => Http::response(str_repeat('x', 176), 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '176',
            ]),
        ]);

        $this->assertNull(app(BookCoverResolver::class)->resolveHanmoto('9784798168494'));
    }
}
