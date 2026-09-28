<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBookLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_lookup_uses_google_books_when_openbd_misses(): void
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
                            'thumbnail' => 'http://books.google.com/books/content?id=RealId&img=1',
                        ],
                    ],
                ]],
            ]),
        ]);

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.lookup', ['isbn' => '9780135957059']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'isbn' => '9780135957059',
                'title' => 'The Pragmatic Programmer',
                'cover' => 'https://books.google.com/books/content?id=RealId&img=1',
                'existingCopies' => 0,
            ]);
    }

    public function test_lookup_finds_cover_by_title_when_isbn_is_placeholder(): void
    {
        Http::fake([
            'api.openbd.jp/*' => Http::response([null]),
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'img.hanmoto.com/*' => Http::response('', 404),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
            'iss.ndl.go.jp/*' => Http::response('', 404),
            'ndlsearch.ndl.go.jp/*' => Http::response('', 404),
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'DUO 3.0',
                        'imageLinks' => [
                            'thumbnail' => 'http://books.google.com/books/content?id=DuoCover&img=1',
                        ],
                    ],
                ]],
            ]),
        ]);

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.lookup', [
                'isbn' => '9789999000012',
                'title' => 'DUO3.0現代英語の重要単語1600',
            ]))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'isbn' => '9789999000012',
                'cover' => 'https://books.google.com/books/content?id=DuoCover&img=1',
            ]);
    }

    public function test_lookup_uses_ndl_when_google_books_quota_is_exceeded(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'error' => ['code' => 429, 'message' => 'Quota exceeded'],
            ], 429),
            'iss.ndl.go.jp/*' => Http::response(
                '<?xml version="1.0"?><rss><channel><item>'
                .'<dc:title>1週間でPHPの基礎が学べる本</dc:title>'
                .'<dc:identifier xsi:type="dcndl:ISBN">9784295013570</dc:identifier>'
                .'</item></channel></rss>'
            ),
            'ndlsearch.ndl.go.jp/*' => Http::response(
                '<?xml version="1.0"?><rss><channel><item>'
                .'<dc:title>1週間でPHPの基礎が学べる本</dc:title>'
                .'<dc:identifier xsi:type="dcndl:ISBN">9784295013570</dc:identifier>'
                .'</item></channel></rss>'
            ),
            'api.openbd.jp/*' => Http::response([[
                'summary' => [
                    'title' => '1週間でPHPの基礎が学べる本',
                    'cover' => 'https://cover.openbd.jp/9784295013570.jpg',
                ],
            ]]),
            'cover.openbd.jp/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'img.hanmoto.com/*' => Http::response('not found', 404),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
        ]);

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.lookup', [
                'isbn' => '9789999000277',
                'title' => '1週間でPHPの基礎が学べる本',
            ]))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'isbn' => '9789999000277',
                'cover' => 'https://cover.openbd.jp/9784295013570.jpg',
                'category' => 'PHP・Laravel',
            ]);
    }

    public function test_lookup_returns_cover_url_when_title_is_missing(): void
    {
        Http::fake([
            'api.openbd.jp/*' => Http::response([null]),
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'img.hanmoto.com/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
            'www.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429),
            'iss.ndl.go.jp/*' => Http::response('', 404),
            'ndlsearch.ndl.go.jp/*' => Http::response('', 404),
        ]);

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.lookup', ['isbn' => '9784798179476']))
            ->assertStatus(404)
            ->assertJson([
                'found' => false,
                'isbn' => '9784798179476',
                'cover' => 'https://img.hanmoto.com/bd/img/9784798179476.jpg',
                'message' => 'タイトルが見つかりませんでした。入力するか、再取得できます。',
            ]);
    }

    public function test_lookup_uses_ndl_isbn_when_openbd_and_google_miss_title(): void
    {
        Http::fake([
            'api.openbd.jp/*' => Http::response([null]),
            'cover.openbd.jp/*' => Http::response('not found', 404),
            'img.hanmoto.com/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
            'www.googleapis.com/*' => Http::response(['error' => ['code' => 429]], 429),
            'iss.ndl.go.jp/*' => Http::response('', 404),
            'ndlsearch.ndl.go.jp/*' => Http::response(
                '<?xml version="1.0"?><rss><channel><item>'
                .'<dc:title>これからはじめるReact実践入門 : コンポーネントの基本からNext.jsによるアプリ開発まで</dc:title>'
                .'<dc:identifier xsi:type="dcndl:ISBN">978-4-8156-3594-7</dc:identifier>'
                .'</item></channel></rss>'
            ),
        ]);

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.lookup', ['isbn' => '9784815635947']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'isbn' => '9784815635947',
                'title' => 'これからはじめるReact実践入門 : コンポーネントの基本からNext.jsによるアプリ開発まで',
                'cover' => 'https://img.hanmoto.com/bd/img/9784815635947.jpg',
                'category' => 'フロントエンド・Web制作',
            ]);
    }

    public function test_lookup_suggests_category_from_title(): void
    {
        Http::fake([
            '*' => Http::response('', 404),
        ]);

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.lookup', [
                'isbn' => '9789999000999',
                'title' => '改訂新版 これからはじめるReact実践入門',
            ]))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'category' => 'フロントエンド・Web制作',
            ]);
    }

    public function test_lookup_keeps_existing_copy_category(): void
    {
        Http::fake([
            '*' => Http::response('', 404),
        ]);

        \App\Models\Book::create([
            'title' => '改訂新版 これからはじめるReact実践入門',
            'isbn' => '9784798179476',
            'copy_number' => 1,
            'category' => 'フロントエンド・Web制作',
            'cover' => null,
            'status' => 'available',
        ]);

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.lookup', ['isbn' => '9784798179476']))
            ->assertOk()
            ->assertJson([
                'found' => true,
                'title' => '改訂新版 これからはじめるReact実践入門',
                'category' => 'フロントエンド・Web制作',
                'existingCopies' => 1,
            ]);
    }
}
