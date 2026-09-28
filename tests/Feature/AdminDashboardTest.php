<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_dashboard_redirects_to_admin_menu(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.menu'));
    }

    public function test_admin_can_open_grafana_dashboard(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user']);

        $available = Book::create([
            'title' => '保管中の本',
            'isbn' => '9784000000001',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'status' => 'available',
        ]);

        $borrowed = Book::create([
            'title' => '期限切れの本',
            'isbn' => '9784000000002',
            'copy_number' => 1,
            'category' => 'データベース',
            'status' => 'rented',
        ]);

        $dueSoon = Book::create([
            'title' => 'まもなく期限の本',
            'isbn' => '9784000000003',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'status' => 'rented',
        ]);

        Loan::create([
            'user_id' => $user->id,
            'book_id' => $borrowed->id,
            'borrowed_at' => now()->subDay(),
            'due_date' => now()->subHours(2),
            'duration_days' => 14,
            'returned_at' => null,
        ]);

        Loan::create([
            'user_id' => $user->id,
            'book_id' => $dueSoon->id,
            'borrowed_at' => now()->subDay(),
            'due_date' => now()->addDays(2),
            'duration_days' => 14,
            'returned_at' => null,
        ]);

        Loan::create([
            'user_id' => $user->id,
            'book_id' => $available->id,
            'borrowed_at' => now()->subDay(),
            'due_date' => now()->subHours(1),
            'duration_days' => 14,
            'returned_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.grafana'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Grafana')
                ->where('stats.books_total', 3)
                ->where('stats.books_titles', 3)
                ->where('stats.books_borrowed', 2)
                ->where('stats.books_available', 1)
                ->where('stats.utilization', 67)
                ->where('stats.overdue', 1)
                ->where('stats.due_soon', 1)
                ->where('stats.users', 1)
                ->where('stats.active_borrowers', 1)
                ->where('stats.loans_today', 0)
                ->where('stats.returns_today', 1)
                ->where('stats.loans_this_month', 3)
                ->has('categories')
                ->has('loansByDay', 14)
                ->has('returnsByDay', 14)
                ->has('topBooks')
                ->missing('topBorrowers')
                ->has('overdueLoans', 1)
                ->where('overdueLoans.0.book.title', '期限切れの本')
                ->has('dueSoonLoans', 1)
                ->where('dueSoonLoans.0.book.title', 'まもなく期限の本'));
    }

    public function test_regular_user_cannot_open_grafana_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->actingAs($user)
            ->get(route('admin.grafana'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_guest_is_redirected_from_grafana_dashboard(): void
    {
        $this->get(route('admin.grafana'))
            ->assertRedirect(route('login'));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
    }
}
