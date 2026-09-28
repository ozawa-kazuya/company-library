<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExportBigQueryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_library_tables_without_secrets(): void
    {
        $directory = sys_get_temp_dir().'/library-bq-'.uniqid('', true);
        mkdir($directory);

        $password = 'secret-password-should-not-export';
        $user = User::factory()->create([
            'name' => '山田 花子',
            'email' => 'hanako@example.test',
            'user_code' => '042',
            'role' => 'user',
            'password' => Hash::make($password),
            'remember_token' => 'remember-token-secret',
        ]);

        $book = Book::create([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'cover' => 'https://example.test/secret-cover.jpg',
            'status' => 'rented',
        ]);

        Loan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(3),
            'due_date' => now()->addDays(11)->endOfDay(),
            'duration_days' => 14,
            'returned_at' => null,
        ]);

        $this->artisan('library:export-bigquery', ['--path' => $directory])
            ->expectsOutputToContain('BigQuery 学習用 CSV を出力しました')
            ->assertSuccessful();

        $usersCsv = (string) file_get_contents($directory.'/users.csv');
        $booksCsv = (string) file_get_contents($directory.'/books.csv');
        $loansCsv = (string) file_get_contents($directory.'/loans.csv');

        $this->assertStringStartsWith("id,name,email,user_code,role\n", $usersCsv);
        $this->assertStringContainsString('山田 花子', $usersCsv);
        $this->assertStringContainsString('042', $usersCsv);
        $this->assertStringNotContainsString('password', $usersCsv);
        $this->assertStringNotContainsString('remember_token', $usersCsv);
        $this->assertStringNotContainsString($password, $usersCsv);
        $this->assertStringNotContainsString('remember-token-secret', $usersCsv);

        $this->assertStringStartsWith("id,title,isbn,copy_number,category,status\n", $booksCsv);
        $this->assertStringContainsString('独習PHP', $booksCsv);
        $this->assertStringContainsString('9784798168494', $booksCsv);
        $this->assertStringNotContainsString('cover', $booksCsv);
        $this->assertStringNotContainsString('secret-cover.jpg', $booksCsv);

        $this->assertStringStartsWith("id,user_id,book_id,borrowed_at,due_date,duration_days,returned_at\n", $loansCsv);
        $this->assertStringContainsString((string) $user->id, $loansCsv);
        $this->assertStringContainsString(',14,', $loansCsv);

        foreach (['users.csv', 'books.csv', 'loans.csv'] as $file) {
            unlink($directory.'/'.$file);
        }
        rmdir($directory);
    }
}
