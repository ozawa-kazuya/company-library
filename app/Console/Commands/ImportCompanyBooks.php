<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Loan;
use Database\Seeders\BookInventorySeeder;
use Illuminate\Console\Command;

class ImportCompanyBooks extends Command
{
    protected $signature = 'books:import-company {--fresh : 既存の書籍・貸出履歴を削除してからインポート}';

    protected $description = '会社書籍在庫管理 Excel から生成した JSON を台帳へ取り込む';

    public function handle(): int
    {
        $jsonPath = database_path('seeders/data/company-books.json');

        if (! is_file($jsonPath)) {
            $this->error("インポートデータがありません: {$jsonPath}");
            $this->line('scripts/generate-company-books-json.py を実行して JSON を生成してください。');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('既存の書籍と貸出履歴をすべて削除します。よろしいですか？')) {
                return self::SUCCESS;
            }

            Loan::query()->delete();
            Book::query()->delete();
            $this->info('既存データを削除しました。');
        }

        $this->call('db:seed', ['--class' => BookInventorySeeder::class]);

        return self::SUCCESS;
    }
}
