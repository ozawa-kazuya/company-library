<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookLoanStats
{
    /**
     * @return array{
     *     stats: array<string, int>,
     *     categories: list<array{name: string, count: int}>,
     *     loansByDay: list<array{day: string, label: string, count: int}>,
     *     returnsByDay: list<array{day: string, label: string, count: int}>,
     *     topBooks: list<array{id: int, name: string, hint: string, count: int}>,
     *     overdueLoans: Collection<int, Loan>,
     *     dueSoonLoans: Collection<int, Loan>
     * }
     */
    public function payload(): array
    {
        $booksTotal = Book::query()->count();
        $booksBorrowed = Loan::query()->whereNull('returned_at')->count();

        return [
            'stats' => [
                'books_total' => $booksTotal,
                'books_titles' => $this->titleCount(),
                'books_borrowed' => $booksBorrowed,
                'books_available' => max(0, $booksTotal - $booksBorrowed),
                'utilization' => $booksTotal > 0 ? (int) round(100 * $booksBorrowed / $booksTotal) : 0,
                'overdue' => Loan::query()->overdue()->count(),
                'due_soon' => $this->dueSoonQuery()->count(),
                'users' => User::query()->where('role', 'user')->count(),
                'active_borrowers' => (int) Loan::query()->whereNull('returned_at')->distinct()->count('user_id'),
                'loans_today' => Loan::query()->whereDate('borrowed_at', today())->count(),
                'returns_today' => Loan::query()->whereNotNull('returned_at')->whereDate('returned_at', today())->count(),
                'loans_this_month' => Loan::query()->where('borrowed_at', '>=', now()->startOfMonth())->count(),
            ],
            'categories' => $this->categories(),
            'loansByDay' => $this->countsByDay('borrowed_at', 13),
            'returnsByDay' => $this->countsByDay('returned_at', 13),
            'topBooks' => $this->topBorrowedBooks(30, 8),
            'overdueLoans' => Loan::query()
                ->overdue()
                ->with([
                    'user:id,name,user_code',
                    'book:id,title,isbn',
                ])
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
            'dueSoonLoans' => $this->dueSoonQuery()
                ->with([
                    'user:id,name,user_code',
                    'book:id,title,isbn',
                ])
                ->orderBy('due_date')
                ->limit(8)
                ->get(),
        ];
    }

    private function titleCount(): int
    {
        $withIsbn = (int) Book::query()
            ->whereNotNull('isbn')
            ->where('isbn', '!=', '')
            ->select('isbn')
            ->distinct()
            ->count('isbn');

        $withoutIsbn = Book::query()
            ->where(function ($query) {
                $query->whereNull('isbn')->orWhere('isbn', '');
            })
            ->count();

        return $withIsbn + $withoutIsbn;
    }

    private function dueSoonQuery()
    {
        return Loan::query()
            ->whereNull('returned_at')
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(3));
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function categories(): array
    {
        $grouped = Book::query()
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->orderByDesc('aggregate')
            ->get();

        $merged = [];

        foreach ($grouped as $row) {
            $name = trim((string) $row->category);
            if ($name === '') {
                $name = '未分類';
            }

            $merged[$name] = ($merged[$name] ?? 0) + (int) $row->aggregate;
        }

        arsort($merged);

        return collect($merged)
            ->map(fn (int $count, string $name) => [
                'name' => $name,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{day: string, label: string, count: int}>
     */
    private function countsByDay(string $column, int $pastDays): array
    {
        $from = now()->subDays($pastDays)->startOfDay();

        $counts = Loan::query()
            ->whereNotNull($column)
            ->where($column, '>=', $from)
            ->get([$column])
            ->countBy(fn (Loan $loan) => $loan->{$column}?->toDateString());

        return collect(range(0, $pastDays))
            ->map(function (int $offset) use ($pastDays, $counts) {
                $date = Carbon::today()->subDays($pastDays - $offset);

                return [
                    'day' => $date->toDateString(),
                    'label' => $date->format('n/j'),
                    'count' => (int) ($counts[$date->toDateString()] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, hint: string, count: int}>
     */
    private function topBorrowedBooks(int $days, int $limit): array
    {
        $rows = Loan::query()
            ->select('book_id', DB::raw('COUNT(*) as aggregate'))
            ->where('borrowed_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('book_id')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();

        $books = Book::query()
            ->whereIn('id', $rows->pluck('book_id'))
            ->get(['id', 'title', 'isbn'])
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($books) {
                $book = $books->get($row->book_id);

                return [
                    'id' => (int) $row->book_id,
                    'name' => $book?->title ?? '不明な資料',
                    'hint' => (string) ($book?->isbn ?? ''),
                    'count' => (int) $row->aggregate,
                ];
            })
            ->values()
            ->all();
    }
}
