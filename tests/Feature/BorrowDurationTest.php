<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BorrowDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'UTC'));
        config(['library.return_location.restricted' => false]);
    }

    public function test_user_can_borrow_for_one_month(): void
    {
        $user = User::factory()->create(['password' => 'changed-password']);
        $book = $this->createBook();

        $this->actingAs($user)
            ->post(route('books.borrow.exec'), [
                'isbn' => $book->isbn,
                'duration' => 30,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success_message');

        $loan = Loan::query()->first();

        $this->assertNotNull($loan);
        $this->assertSame(30, (int) $loan->duration_days);
        $this->assertSame(
            now()->addMonth()->endOfDay()->format('Y-m-d H:i:s'),
            $loan->due_date->format('Y-m-d H:i:s'),
        );
    }

    public function test_one_month_duration_rejects_unknown_value(): void
    {
        $user = User::factory()->create(['password' => 'changed-password']);
        $book = $this->createBook();

        $this->actingAs($user)
            ->from(route('books.borrowScan'))
            ->post(route('books.borrow.exec'), [
                'isbn' => $book->isbn,
                'duration' => 31,
            ])
            ->assertRedirect(route('books.borrowScan'))
            ->assertSessionHasErrors('duration');

        $this->assertDatabaseCount('loans', 0);
    }

    public function test_one_minute_demo_duration_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'changed-password']);
        $book = $this->createBook();

        $this->actingAs($user)
            ->from(route('books.borrowScan'))
            ->post(route('books.borrow.exec'), [
                'isbn' => $book->isbn,
                'duration' => 1,
            ])
            ->assertRedirect(route('books.borrowScan'))
            ->assertSessionHasErrors('duration');

        $this->assertDatabaseCount('loans', 0);
    }

    private function createBook(): Book
    {
        return Book::create([
            'title' => '独習PHP',
            'isbn' => '9784798168494',
            'copy_number' => 1,
            'category' => 'PHP・Laravel',
            'cover' => null,
            'status' => 'available',
        ]);
    }
}
