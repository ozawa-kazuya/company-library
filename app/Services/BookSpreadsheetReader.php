<?php

namespace App\Services;

use App\Support\BookCategories;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class BookSpreadsheetReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(string $path, ?string $extension = null): array
    {
        $format = $this->resolveFormat($path, $extension);

        $rows = match ($format) {
            'csv' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new InvalidArgumentException('対応形式は CSV または XLSX です。'),
        };

        return $this->normalizeRows($rows);
    }

    private function resolveFormat(string $path, ?string $extension): string
    {
        if ($this->isXlsx($path)) {
            return 'xlsx';
        }

        $extension = strtolower(trim((string) ($extension ?: pathinfo($path, PATHINFO_EXTENSION))));

        return match ($extension) {
            'xlsx' => 'xlsx',
            'csv', 'txt' => 'csv',
            default => 'csv',
        };
    }

    private function isXlsx(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 4);
        fclose($handle);

        return $header === "PK\x03\x04";
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsv(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('CSV ファイルを開けませんでした。');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        if (! mb_check_encoding($content, 'UTF-8')) {
            $converted = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');

            if ($converted !== false) {
                $content = $converted;
            }
        }

        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            throw new RuntimeException('CSV ファイルを開けませんでした。');
        }

        fwrite($handle, $content);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(
                fn ($cell) => $cell === null || $cell === '' ? null : trim((string) $cell),
                $row
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('XLSX ファイルを開けませんでした。');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            $zip->close();
            throw new RuntimeException('XLSX のシートを読み取れませんでした。');
        }

        $zip->close();

        $sheet = simplexml_load_string($sheetXml);

        if ($sheet === false) {
            throw new RuntimeException('XLSX のシート形式が不正です。');
        }

        $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];

        foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
            $cells = [];
            $columnIndex = 0;

            foreach ($row->c as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $targetIndex = $reference !== ''
                    ? $this->columnIndexFromReference($reference)
                    : $columnIndex;

                while (count($cells) < $targetIndex) {
                    $cells[] = null;
                }

                $value = $this->cellValue($cell, $sharedStrings);
                $cells[$targetIndex] = $value === '' ? null : $value;
                $columnIndex = max($columnIndex, $targetIndex + 1);
            }

            if ($this->rowHasContent($cells)) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $shared = simplexml_load_string($xml);

        if ($shared === false) {
            return [];
        }

        $shared->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($shared->xpath('//m:si') ?: [] as $item) {
            $strings[] = $this->sharedStringText($item);
        }

        return $strings;
    }

    private function sharedStringText(\SimpleXMLElement $item): string
    {
        $item->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        if (isset($item->t)) {
            return trim((string) $item->t);
        }

        $parts = [];

        foreach ($item->xpath('./m:r') ?: [] as $run) {
            $run->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            if (isset($run->t)) {
                $parts[] = (string) $run->t;
            }
        }

        if ($parts === []) {
            foreach ($item->xpath('.//m:t[not(ancestor::m:rPh)]') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }
        }

        return trim(implode('', $parts));
    }

    private function inlineStringText(?\SimpleXMLElement $inlineString): ?string
    {
        if ($inlineString === null) {
            return null;
        }

        $inlineString->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        if (isset($inlineString->t)) {
            return trim((string) $inlineString->t);
        }

        $parts = [];

        foreach ($inlineString->xpath('./m:r') ?: [] as $run) {
            $run->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            if (isset($run->t)) {
                $parts[] = (string) $run->t;
            }
        }

        if ($parts === []) {
            foreach ($inlineString->xpath('.//m:t[not(ancestor::m:rPh)]') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }
        }

        $text = trim(implode('', $parts));

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<int, string|null>  $cells
     */
    private function cellValue(\SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return $this->inlineStringText($cell->is ?? null);
        }

        $value = isset($cell->v) ? (string) $cell->v : null;

        if ($value === null || $value === '') {
            return null;
        }

        if ($type === 's') {
            return trim($sharedStrings[(int) $value] ?? '');
        }

        return trim($value);
    }

    private function columnIndexFromReference(string $reference): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($reference), $matches);
        $letters = $matches[1] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * @param  array<int, string|null>  $cells
     */
    private function rowHasContent(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && $cell !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headerRowIndex = $this->detectHeaderRowIndex($rows);
        $headers = array_map(
            fn ($header) => $this->normalizeHeader((string) $header),
            $rows[$headerRowIndex] ?? []
        );

        $entries = [];

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $mapped = $this->mapRow($headers, $row);

            if ($mapped === null) {
                continue;
            }

            $entries[] = $mapped;
        }

        return $entries;
    }

    private function detectHeaderRowIndex(array $rows): int
    {
        $knownHeaders = ['題名', 'タイトル', 'title', 'isbn', '在庫数'];

        foreach ($rows as $index => $row) {
            foreach ($row as $cell) {
                $normalized = $this->normalizeHeader((string) $cell);

                if (in_array($normalized, $knownHeaders, true)) {
                    return $index;
                }

                if (str_contains($normalized, '題名') || str_contains($normalized, 'タイトル')) {
                    return $index;
                }
            }
        }

        return 0;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $withoutTrailingKana = preg_replace('/[\x{30A0}-\x{30FF}\x{3040}-\x{309F}]+$/u', '', $header) ?? $header;
        $withoutTrailingKana = trim($withoutTrailingKana);

        if ($withoutTrailingKana !== '') {
            $header = $withoutTrailingKana;
        }

        $header = mb_strtolower(trim($header));

        return match ($header) {
            'タイトル', 'title', '書名', '題名' => '題名',
            'isbn', 'isbnコード', 'isbnコード（13桁）' => 'isbn',
            '著者', '著者名', 'author' => '著者',
            'カテゴリ', 'category', '分類' => 'カテゴリ',
            '在庫数', '在庫', 'available', '冊数', '部数', '数量' => '在庫数',
            '表紙', 'cover', '表紙url', 'coverurl', 'cover url' => '表紙',
            default => $header,
        };
    }

    /**
     * @param  array<int, string|null>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $headers, array $row): ?array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $data[$header] = $row[$index] ?? null;
        }

        $title = trim((string) ($data['題名'] ?? ''));

        if ($title === '') {
            return null;
        }

        $stock = $this->resolveStockCount($data);

        $isbn = preg_replace('/[^0-9Xx]/', '', (string) ($data['isbn'] ?? '')) ?: null;
        $category = trim((string) ($data['カテゴリ'] ?? ''));
        $cover = trim((string) ($data['表紙'] ?? ''));

        return [
            'excel_title' => $title,
            'stock_copies' => $stock,
            'isbn' => $isbn,
            'category' => BookCategories::normalize($category),
            'cover' => $cover !== '' ? $cover : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveStockCount(array $data): int
    {
        if (array_key_exists('在庫数', $data) && $data['在庫数'] !== null && $data['在庫数'] !== '') {
            return max(0, (int) $data['在庫数']);
        }

        // 旧形式Excel（総冊数のみ）との互換
        if (array_key_exists('総冊数', $data) && $data['総冊数'] !== null && $data['総冊数'] !== '') {
            return max(0, (int) $data['総冊数']);
        }

        return 1;
    }
}
