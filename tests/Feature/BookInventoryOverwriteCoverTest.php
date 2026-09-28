<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\BookInventoryImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookInventoryOverwriteCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_overwrite_keeps_manually_set_cover(): void
    {
        Http::fake([
            '*' => Http::response('not found', 404),
        ]);

        $cover = '/covers/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        Book::create([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'cover' => $cover,
            'status' => 'available',
        ]);

        app(BookInventoryImporter::class)->import(
            [
                [
                    'excel_title' => '独習PHP',
                    'isbn' => '9784798168494',
                    'category' => 'PHP・Laravel',
                    'stock_copies' => 1,
                ],
            ],
            lookupMissingIsbn: false,
            overwriteExisting: true,
        );

        $this->assertDatabaseHas('books', [
            'isbn' => '9784798168494',
            'cover' => $cover,
        ]);
        $this->assertSame(1, Book::where('isbn', '9784798168494')->count());
    }

    public function test_overwrite_keeps_cover_when_isbn_is_blank_in_excel(): void
    {
        $cover = 'https://example.com/custom-cover.jpg';

        Book::create([
            'title' => '社内研修テキスト',
            'isbn' => '9789999000012',
            'copy_number' => 1,
            'category' => 'ソフトウェア開発',
            'cover' => $cover,
            'status' => 'available',
        ]);

        app(BookInventoryImporter::class)->import(
            [
                [
                    'excel_title' => '社内研修テキスト',
                    'isbn' => '',
                    'category' => 'ソフトウェア開発',
                    'stock_copies' => 1,
                ],
            ],
            lookupMissingIsbn: false,
            overwriteExisting: true,
        );

        $this->assertTrue(
            Book::where('title', '社内研修テキスト')->get()->every(
                fn (Book $book) => $book->cover === $cover,
            ),
        );
    }

    public function test_additional_copies_inherit_pasted_cover(): void
    {
        Http::fake([
            '*' => Http::response('not found', 404),
        ]);

        $cover = 'https://example.com/custom-cover.jpg';

        Book::create([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'cover' => $cover,
            'status' => 'available',
        ]);

        app(BookInventoryImporter::class)->import(
            [
                [
                    'excel_title' => '独習PHP',
                    'isbn' => '9784798168494',
                    'category' => 'PHP・Laravel',
                    'stock_copies' => 1,
                ],
            ],
            lookupMissingIsbn: false,
            overwriteExisting: false,
        );

        $this->assertSame(2, Book::where('isbn', '9784798168494')->count());
        $this->assertTrue(
            Book::where('isbn', '9784798168494')->get()->every(
                fn (Book $book) => $book->cover === $cover,
            ),
        );
    }

    public function test_excel_cover_url_is_stored_for_all_copies(): void
    {
        Http::fake([
            'example.com/*' => Http::response(str_repeat('x', 2000), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            '*' => Http::response('not found', 404),
        ]);

        app(BookInventoryImporter::class)->import(
            [
                [
                    'excel_title' => '独習PHP',
                    'isbn' => '9784798168494',
                    'category' => 'PHP・Laravel',
                    'stock_copies' => 2,
                    'cover' => 'https://example.com/cover.jpg',
                ],
            ],
            lookupMissingIsbn: false,
            overwriteExisting: false,
        );

        $books = Book::where('isbn', '9784798168494')->get();
        $this->assertCount(2, $books);
        $this->assertTrue(
            $books->every(fn (Book $book) => str_starts_with((string) $book->cover, '/covers/')),
        );
        $this->assertSame($books[0]->cover, $books[1]->cover);
        $this->assertDatabaseCount('cover_images', 1);
    }
}
