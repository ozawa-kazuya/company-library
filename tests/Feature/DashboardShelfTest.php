<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardShelfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_employee_dashboard_does_not_include_borrower_names(): void
    {
        $viewer = User::factory()->create(['password' => 'changed-password']);
        $borrower = User::factory()->create([
            'name' => '山田 花子',
            'password' => 'changed-password',
        ]);
        $book = Book::create([
            'title' => '独習C#',
            'isbn' => '9784798153827',
            'copy_number' => 1,
            'category' => 'C#・.NET',
            'status' => 'rented',
        ]);

        Loan::create([
            'user_id' => $borrower->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(4),
            'due_date' => now()->addDays(10),
            'duration_days' => 14,
            'returned_at' => null,
        ]);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('books', 1)
                ->has('books.0.loans', 1)
                ->missing('books.0.loans.0.user')
                ->missing('books.0.loans.0.user_id'));
    }

    public function test_admin_book_search_still_includes_borrower_names(): void
    {
        $admin = User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
        $borrower = User::factory()->create([
            'name' => '山田 花子',
            'password' => 'changed-password',
        ]);
        $book = Book::create([
            'title' => '独習C#',
            'isbn' => '9784798153827',
            'copy_number' => 1,
            'category' => 'C#・.NET',
            'status' => 'rented',
        ]);

        Loan::create([
            'user_id' => $borrower->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDays(4),
            'due_date' => now()->addDays(10),
            'duration_days' => 14,
            'returned_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.search.books'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Search')
                ->where('books.0.loans.0.user.name', '山田 花子'));
    }
}
