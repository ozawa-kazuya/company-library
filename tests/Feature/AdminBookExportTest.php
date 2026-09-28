<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Services\BookSpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class AdminBookExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_download_registered_books_as_xlsx(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
        $borrower = User::factory()->create([
            'name' => '山田 花子',
            'password' => 'changed-password',
        ]);

        Book::create([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'cover' => 'https://cover.openbd.jp/9784798168494.jpg',
            'status' => 'available',
        ]);
        Book::create([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 2,
            'category' => 'PHP・Laravel',
            'cover' => 'https://cover.openbd.jp/9784798168494.jpg',
            'status' => 'available',
        ]);
        $react = Book::create([
            'title' => 'これからはじめるReact実践入門',
            'isbn' => '9784815635947',
            'copy_number' => 1,
            'category' => 'フロントエンド・Web制作',
            'cover' => 'https://img.hanmoto.com/bd/img/9784815635947.jpg',
            'status' => 'rented',
        ]);

        Loan::create([
            'user_id' => $borrower->id,
            'book_id' => $react->id,
            'borrowed_at' => now()->subDays(2),
            'due_date' => now()->addDays(12)->endOfDay(),
            'duration_days' => 14,
            'returned_at' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.books.export'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('.xlsx', $disposition);
        $this->assertStringContainsString(rawurlencode('社内図書台帳'), $disposition);

        $path = sys_get_temp_dir().'/library-export-'.uniqid('', true).'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $this->assertSame("PK\x03\x04", substr((string) file_get_contents($path), 0, 4));

            $entries = app(BookSpreadsheetReader::class)->read($path, 'xlsx');
            $byIsbn = collect($entries)->keyBy('isbn');

            $this->assertSame('独習PHP', $byIsbn['9784798168494']['excel_title']);
            $this->assertSame(2, $byIsbn['9784798168494']['stock_copies']);
            $this->assertSame('PHP・Laravel', $byIsbn['9784798168494']['category']);
            $this->assertSame('https://cover.openbd.jp/9784798168494.jpg', $byIsbn['9784798168494']['cover']);
            $this->assertSame('これからはじめるReact実践入門', $byIsbn['9784815635947']['excel_title']);
            $this->assertSame(1, $byIsbn['9784815635947']['stock_copies']);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $workbookXml = (string) $zip->getFromName('xl/workbook.xml');
            $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertFalse($zip->locateName('xl/worksheets/sheet2.xml'));
            $zip->close();

            $this->assertStringContainsString('一括登録用', $workbookXml);
            $this->assertStringNotContainsString('冊別明細', $workbookXml);
            $this->assertStringContainsString('9784798168494', $sheetXml);
            $this->assertStringContainsString('9784815635947', $sheetXml);
            $this->assertStringNotContainsString('保管中', $sheetXml);
            $this->assertStringNotContainsString('貸出中', $sheetXml);
            $this->assertStringNotContainsString('山田 花子', $sheetXml);
            $this->assertStringNotContainsString('9.784E+', $sheetXml);
        } finally {
            @unlink($path);
        }
    }

    public function test_admin_can_download_empty_catalog(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.books.export'))
            ->assertOk();

        $path = sys_get_temp_dir().'/library-export-empty-'.uniqid('', true).'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $entries = app(BookSpreadsheetReader::class)->read($path, 'xlsx');
            $this->assertSame([], $entries);
        } finally {
            @unlink($path);
        }
    }

    public function test_reader_maps_cover_column_from_csv(): void
    {
        $path = sys_get_temp_dir().'/library-cover-'.uniqid('', true).'.csv';
        file_put_contents(
            $path,
            "題名,ISBN,カテゴリ,在庫数,表紙\n独習PHP,9784798168494,PHP・Laravel,2,https://example.com/cover.jpg\n",
        );

        try {
            $entries = app(BookSpreadsheetReader::class)->read($path, 'csv');
            $this->assertSame('https://example.com/cover.jpg', $entries[0]['cover']);
            $this->assertSame(2, $entries[0]['stock_copies']);
        } finally {
            @unlink($path);
        }
    }

    public function test_non_admin_cannot_export_books(): void
    {
        $user = User::factory()->create(['password' => 'changed-password']);

        $this->actingAs($user)
            ->get(route('admin.books.export'))
            ->assertRedirect(route('dashboard'));
    }
}
