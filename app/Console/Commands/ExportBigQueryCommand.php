<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportBigQueryCommand extends Command
{
    protected $signature = 'library:export-bigquery
                            {--path= : CSV の出力先（省略時は storage/app/bigquery）}';

    protected $description = '学習用 BigQuery 投入向けに users / books / loans を CSV 出力する（パスワード・表紙は出さない）';

    public function handle(): int
    {
        $directory = $this->option('path') ?: storage_path('app/bigquery');
        File::ensureDirectoryExists($directory);

        $usersPath = $this->writeCsv($directory.'/users.csv', ['id', 'name', 'email', 'user_code', 'role'], function ($handle) {
            User::query()
                ->orderBy('id')
                ->select(['id', 'name', 'email', 'user_code', 'role'])
                ->each(function (User $user) use ($handle) {
                    fputcsv($handle, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->user_code,
                        $user->role,
                    ]);
                }, 200);
        });

        $booksPath = $this->writeCsv($directory.'/books.csv', ['id', 'title', 'isbn', 'copy_number', 'category', 'status'], function ($handle) {
            Book::query()
                ->orderBy('id')
                ->select(['id', 'title', 'isbn', 'copy_number', 'category', 'status'])
                ->each(function (Book $book) use ($handle) {
                    fputcsv($handle, [
                        $book->id,
                        $book->title,
                        $book->isbn,
                        $book->copy_number,
                        $book->category,
                        $book->status,
                    ]);
                }, 200);
        });

        $loansPath = $this->writeCsv($directory.'/loans.csv', [
            'id',
            'user_id',
            'book_id',
            'borrowed_at',
            'due_date',
            'duration_days',
            'returned_at',
        ], function ($handle) {
            Loan::query()
                ->orderBy('id')
                ->select([
                    'id',
                    'user_id',
                    'book_id',
                    'borrowed_at',
                    'due_date',
                    'duration_days',
                    'returned_at',
                ])
                ->each(function (Loan $loan) use ($handle) {
                    fputcsv($handle, [
                        $loan->id,
                        $loan->user_id,
                        $loan->book_id,
                        $this->timestamp($loan->borrowed_at),
                        $this->timestamp($loan->due_date),
                        $loan->duration_days,
                        $this->timestamp($loan->returned_at),
                    ]);
                }, 200);
        });

        $this->info('BigQuery 学習用 CSV を出力しました。');
        $this->line($usersPath);
        $this->line($booksPath);
        $this->line($loansPath);
        $this->comment('パスワード・remember_token・表紙は含めていません。投入手順は docs/BigQuery連携.md です。');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $headers
     * @param  callable(resource): void  $writeRows
     */
    private function writeCsv(string $path, array $headers, callable $writeRows): string
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("CSV を開けません: {$path}");
        }

        try {
            fputcsv($handle, $headers);
            $writeRows($handle);
        } finally {
            fclose($handle);
        }

        return $path;
    }

    private function timestamp(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->utc()->format('Y-m-d H:i:s');
        }

        return '';
    }
}
