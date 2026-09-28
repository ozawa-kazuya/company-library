<?php

namespace Database\Seeders;

use App\Services\BookInventoryImporter;
use Illuminate\Database\Seeder;

class BookInventorySeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/data/company-books.json');

        if (! is_file($jsonPath)) {
            $this->command?->error("インポートデータが見つかりません: {$jsonPath}");
            $this->command?->line('scripts/generate-company-books-json.py を実行するか、管理画面の一括登録を利用してください。');

            return;
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($entries)) {
            $this->command?->error('JSON の形式が不正です。');

            return;
        }

        $normalized = array_map(function (array $entry): array {
            $lookup = $entry['lookup'] ?? [];

            return [
                'excel_title' => (string) $entry['excel_title'],
                'stock_copies' => (int) ($entry['available_copies'] ?? $entry['total_copies'] ?? 1),
                'isbn' => $lookup['isbn'] ?? null,
                'category' => null,
            ];
        }, $entries);

        $result = app(BookInventoryImporter::class)->import(
            $normalized,
            lookupMissingIsbn: true,
        );

        $this->command?->info(sprintf(
            '書籍 %d 冊を登録しました（ISBN自動検索 %d 件、仮ISBN %d 件）。',
            $result['created_books'],
            $result['resolved_isbn'],
            $result['placeholder_isbn'],
        ));

        foreach ($result['warnings'] as $warning) {
            $this->command?->warn($warning);
        }
    }
}
