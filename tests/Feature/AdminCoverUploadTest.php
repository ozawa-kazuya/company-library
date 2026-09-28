<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CoverImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminCoverUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_upload_cover_image(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->post(route('admin.books.covers.store'), [
                'cover' => $this->coverFile(),
            ]);

        $response->assertOk()->assertJsonStructure(['url']);

        $url = $response->json('url');
        $this->assertMatchesRegularExpression('#^/covers/[0-9a-f-]{36}$#i', $url);

        $id = basename($url);
        $this->assertDatabaseHas('cover_images', [
            'id' => $id,
            'mime' => 'image/jpeg',
        ]);

        $image = CoverImage::find($id);
        $this->assertNotNull($image);
        $this->assertGreaterThan(32, $image->byte_size);
        $this->assertNotEmpty($image->data);
    }

    public function test_logged_in_user_can_view_uploaded_cover(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $url = $this->actingAs($admin)
            ->post(route('admin.books.covers.store'), [
                'cover' => $this->coverFile(),
            ])
            ->json('url');

        $id = basename($url);
        $stored = CoverImage::findOrFail($id);

        $response = $this->actingAs($user)->get(route('covers.show', $id));

        $response->assertOk();
        $this->assertSame($stored->mime, $response->headers->get('Content-Type'));
        $this->assertSame($stored->data, $response->getContent());
    }

    public function test_guest_cannot_upload_or_view_cover(): void
    {
        $this->post(route('admin.books.covers.store'), [
            'cover' => $this->coverFile(),
        ])->assertRedirect(route('login'));

        $image = CoverImage::create([
            'mime' => 'image/jpeg',
            'byte_size' => 100,
            'data' => str_repeat('x', 100),
        ]);

        $this->get(route('covers.show', $image->id))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_upload_cover(): void
    {
        $user = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->actingAs($user)
            ->post(route('admin.books.covers.store'), [
                'cover' => $this->coverFile(),
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('cover_images', 0);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $admin = $this->admin();
        $path = storage_path('framework/testing-cover.txt');
        file_put_contents($path, 'not an image');

        $this->actingAs($admin)
            ->from(route('admin.books.create'))
            ->post(route('admin.books.covers.store'), [
                'cover' => new UploadedFile($path, 'cover.txt', 'text/plain', null, true),
            ])
            ->assertSessionHasErrors('cover');

        $this->assertDatabaseCount('cover_images', 0);
        @unlink($path);
    }

    public function test_admin_can_register_book_with_uploaded_cover(): void
    {
        $admin = $this->admin();
        $existing = $this->createBook(['cover' => null]);

        $url = $this->actingAs($admin)
            ->post(route('admin.books.covers.store'), [
                'cover' => $this->coverFile(),
            ])
            ->json('url');

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'isbn' => $existing->isbn,
                'title' => $existing->title,
                'category' => $existing->category,
                'cover' => $url,
            ])
            ->assertRedirect(route('admin.books.create'))
            ->assertSessionHas('success_message');

        $this->assertDatabaseHas('books', [
            'id' => $existing->id,
            'cover' => $url,
        ]);
        $this->assertSame(2, Book::where('isbn', $existing->isbn)->count());
        $this->assertTrue(
            Book::where('isbn', $existing->isbn)->get()->every(fn (Book $book) => $book->cover === $url),
        );
    }

    public function test_admin_can_register_book_with_cover_url_and_category(): void
    {
        Http::fake([
            'img.hanmoto.com/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), [
                'isbn' => '9784798179476',
                'title' => '改訂新版 これからはじめるReact実践入門',
                'category' => 'フロントエンド・Web制作',
                'cover' => 'https://img.hanmoto.com/bd/img/9784798179476.jpg',
            ])
            ->assertRedirect(route('admin.books.create'))
            ->assertSessionHas('success_message');

        $book = Book::where('isbn', '9784798179476')->first();
        $this->assertNotNull($book);
        $this->assertSame('改訂新版 これからはじめるReact実践入門', $book->title);
        $this->assertSame('フロントエンド・Web制作', $book->category);
        $this->assertTrue(str_starts_with((string) $book->cover, '/covers/'));
        $this->assertDatabaseCount('cover_images', 1);
    }

    public function test_admin_can_persist_pasted_cover_url(): void
    {
        Http::fake([
            'example.com/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.books.covers.from-url'), [
                'url' => 'https://example.com/cover.jpg',
            ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertMatchesRegularExpression('#^/covers/[0-9a-f-]{36}$#i', $response->json('url'));
        $this->assertDatabaseCount('cover_images', 1);
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

    private function coverFile(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/fixtures/cover.jpg'),
            'cover.jpg',
            'image/jpeg',
            null,
            true,
        );
    }
}
