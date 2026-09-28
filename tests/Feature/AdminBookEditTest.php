<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBookEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_edit_page_includes_cover(): void
    {
        $admin = $this->admin();
        $book = $this->createBook([
            'cover' => 'https://cover.openbd.jp/9784798168494.jpg',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.books.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/BookEdit')
                ->where('books.0.id', $book->id)
                ->where('books.0.cover', 'https://cover.openbd.jp/9784798168494.jpg'));
    }

    public function test_admin_can_update_cover_url(): void
    {
        Http::fake([
            'img.hanmoto.com/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $admin = $this->admin();
        $book = $this->createBook(['cover' => null]);

        $this->actingAs($admin)
            ->put(route('admin.books.bulk-update'), [
                'books' => [
                    [
                        'id' => $book->id,
                        'title' => $book->title,
                        'isbn' => $book->isbn,
                        'category' => $book->category,
                        'cover' => 'https://img.hanmoto.com/bd/img/9784798168494.jpg',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.books.edit'))
            ->assertSessionHas('success_message');

        $book->refresh();
        $this->assertTrue(str_starts_with((string) $book->cover, '/covers/'));
        $this->assertDatabaseCount('cover_images', 1);
    }

    public function test_empty_cover_is_cleared(): void
    {
        $admin = $this->admin();
        $book = $this->createBook([
            'cover' => 'https://cover.openbd.jp/9784798168494.jpg',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.books.bulk-update'), [
                'books' => [
                    [
                        'id' => $book->id,
                        'title' => $book->title,
                        'isbn' => $book->isbn,
                        'category' => $book->category,
                        'cover' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.books.edit'));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'cover' => null,
        ]);
    }

    public function test_invalid_cover_url_is_rejected(): void
    {
        $admin = $this->admin();
        $book = $this->createBook(['cover' => null]);

        $this->actingAs($admin)
            ->from(route('admin.books.edit'))
            ->put(route('admin.books.bulk-update'), [
                'books' => [
                    [
                        'id' => $book->id,
                        'title' => $book->title,
                        'isbn' => $book->isbn,
                        'category' => $book->category,
                        'cover' => 'not-a-url',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.books.edit'))
            ->assertSessionHasErrors('books.0.cover');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'cover' => null,
        ]);
    }

    public function test_local_cover_path_is_accepted(): void
    {
        $admin = $this->admin();
        $book = $this->createBook(['cover' => null]);
        $cover = '/covers/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->actingAs($admin)
            ->put(route('admin.books.bulk-update'), [
                'books' => [
                    [
                        'id' => $book->id,
                        'title' => $book->title,
                        'isbn' => $book->isbn,
                        'category' => $book->category,
                        'cover' => $cover,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.books.edit'))
            ->assertSessionHas('success_message');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'cover' => $cover,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
    }

    private function createBook(array $overrides = []): Book
    {
        return Book::create(array_merge([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'cover' => null,
            'status' => 'available',
        ], $overrides));
    }
}
