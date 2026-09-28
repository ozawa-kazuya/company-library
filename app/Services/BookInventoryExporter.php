<?php

namespace App\Services;

use App\Models\Book;

class BookInventoryExporter
{
    /**
     * @return list<array{name: string, rows: list<list<string>>}>
     */
    public function sheets(): array
    {
        $books = Book::query()
            ->orderBy('title')
            ->orderBy('isbn')
            ->orderBy('copy_number')
            ->orderBy('id')
            ->get();

        return [
            [
                'name' => '一括登録用',
                'rows' => $this->groupedRows($books),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Book>  $books
     * @return list<list<string>>
     */
    private function groupedRows($books): array
    {
        $rows = [[
            '題名',
            'ISBN',
            'カテゴリ',
            '在庫数',
            '表紙',
        ]];

        $groups = $books->groupBy(function (Book $book) {
            $isbn = trim((string) ($book->isbn ?? ''));

            return $isbn !== '' ? $isbn : 'id-'.$book->id;
        });

        foreach ($groups as $copies) {
            $first = $copies->first();
            $total = $copies->count();

            $rows[] = [
                (string) $first->title,
                (string) ($first->isbn ?? ''),
                (string) ($first->category ?? ''),
                (string) $total,
                (string) ($first->cover ?? ''),
            ];
        }

        return $rows;
    }
}
