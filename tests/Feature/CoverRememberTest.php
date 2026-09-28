<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoverRememberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_user_can_remember_displayed_cover_as_stored_image(): void
    {
        Http::fake([
            'cover.openbd.jp/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $user = User::factory()->create(['password' => 'changed-password']);
        $book = $this->createBook(['cover' => null]);

        $this->actingAs($user)
            ->postJson(route('books.covers.remember'), [
                'isbn' => $book->isbn,
                'url' => 'https://cover.openbd.jp/9784798168494.jpg',
            ])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $book->refresh();
        $this->assertTrue(str_starts_with((string) $book->cover, '/covers/'));
        $this->assertDatabaseCount('cover_images', 1);
    }

    public function test_remember_keeps_url_when_download_fails(): void
    {
        Http::fake([
            'cover.openbd.jp/*' => Http::response('forbidden', 403),
        ]);

        $user = User::factory()->create(['password' => 'changed-password']);
        $book = $this->createBook(['cover' => null]);
        $url = 'https://cover.openbd.jp/9784798168494.jpg';

        $this->actingAs($user)
            ->postJson(route('books.covers.remember'), [
                'isbn' => $book->isbn,
                'url' => $url,
            ])
            ->assertOk()
            ->assertJson(['saved' => true, 'url' => $url]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'cover' => $url,
        ]);
    }

    public function test_remember_does_not_overwrite_uploaded_cover(): void
    {
        $user = User::factory()->create(['password' => 'changed-password']);
        $local = '/covers/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $book = $this->createBook(['cover' => $local]);

        $this->actingAs($user)
            ->postJson(route('books.covers.remember'), [
                'isbn' => $book->isbn,
                'url' => 'https://cover.openbd.jp/9784798168494.jpg',
            ])
            ->assertOk()
            ->assertJson(['saved' => false]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'cover' => $local,
        ]);
    }

    public function test_guest_cannot_remember_cover(): void
    {
        $this->postJson(route('books.covers.remember'), [
            'isbn' => '9784798168494',
            'url' => 'https://cover.openbd.jp/9784798168494.jpg',
        ])->assertRedirect(route('login'));
    }

    public function test_remember_rejects_unknown_host(): void
    {
        $user = User::factory()->create(['password' => 'changed-password']);
        $book = $this->createBook(['cover' => null]);

        $this->actingAs($user)
            ->postJson(route('books.covers.remember'), [
                'isbn' => $book->isbn,
                'url' => 'https://evil.example/steal',
            ])
            ->assertStatus(422);
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
