<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_legacy_search_url_redirects_to_book_search(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.search'))
            ->assertRedirect(route('admin.search.books'));
    }

    public function test_admin_can_open_book_search(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['role' => 'user', 'password' => 'changed-password']);
        $book = Book::create([
            'title' => '検索用の本',
            'isbn' => '9784000000099',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'status' => 'rented',
        ]);

        Loan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'borrowed_at' => now()->subDay(),
            'due_date' => now()->addDays(7),
            'duration_days' => 14,
            'returned_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.search.books'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Search')
                ->has('books', 1)
                ->missing('users')
                ->where('stats.total', 1)
                ->where('stats.borrowed', 1));
    }

    public function test_admin_can_open_user_search(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'role' => 'user',
            'name' => '山田 太郎',
            'password' => 'changed-password',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.search.users'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/UserSearch')
                ->has('users', 1)
                ->where('users.0.id', $user->id)
                ->missing('books'));
    }

    public function test_regular_user_cannot_open_book_or_user_search(): void
    {
        $user = User::factory()->create([
            'password' => 'changed-password',
        ]);

        $this->actingAs($user)
            ->get(route('admin.search.books'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get(route('admin.search.users'))
            ->assertRedirect(route('dashboard'));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'user_code' => '001',
            'password' => 'changed-password',
        ]);
    }
}
