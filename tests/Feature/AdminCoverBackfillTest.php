<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminCoverBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_backfill_missing_covers(): void
    {
        Http::fake([
            'api.openbd.jp/*' => Http::response([null]),
            'cover.openbd.jp/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'img.hanmoto.com/*' => Http::response('not found', 404),
            'covers.openlibrary.org/*' => Http::response('not found', 404),
            'iss.ndl.go.jp/*' => Http::response('', 404),
            'ndlsearch.ndl.go.jp/*' => Http::response('', 404),
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

        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
        $empty = Book::create([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'cover' => null,
            'status' => 'available',
        ]);
        $kept = Book::create([
            'title' => '他の本',
            'isbn' => '9784873115658',
            'copy_number' => 1,
            'category' => 'ソフトウェア開発',
            'cover' => '/covers/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'status' => 'available',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.books.covers.backfill'))
            ->assertRedirect(route('admin.books.edit'))
            ->assertSessionHas('success_message');

        $empty->refresh();
        $kept->refresh();

        $this->assertNotEmpty($empty->cover);
        $this->assertSame('/covers/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $kept->cover);
    }

    public function test_non_admin_cannot_backfill(): void
    {
        $user = User::factory()->create(['password' => 'changed-password']);

        $this->actingAs($user)
            ->post(route('admin.books.covers.backfill'))
            ->assertRedirect(route('dashboard'));
    }
}
