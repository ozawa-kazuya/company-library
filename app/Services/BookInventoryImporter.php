<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Loan;
use App\Support\BookCoverRules;
use App\Support\CoverSourceGuard;
use Illuminate\Support\Facades\DB;

class BookInventoryImporter
{
    public function __construct(
        private BookCategoryGuesser $categoryGuesser,
        private NdlBookLookup $ndlLookup,
        private GoogleBooksLookup $googleBooks,
        private BookIsbnLookup $isbnLookup,
        private RemoteCoverStore $remoteCoverStore,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{
     *     created_books: int,
     *     resolved_isbn: int,
     *     placeholder_isbn: int,
     *     skipped: int,
     *     replaced_books: int,
     *     warnings: array<int, string>
     * }
     */
    public function import(
        array $entries,
        bool $lookupMissingIsbn = false,
        bool $overwriteExisting = false,
    ): array {
        set_time_limit(0);

        $createdBooks = 0;
        $resolvedIsbn = 0;
        $placeholderIsbn = 0;
        $skipped = 0;
        $replacedBooks = 0;
        $warnings = [];
        $coverCache = [];
        $isbnByTitle = [];
        $importedIsbns = [];
        $preservedCovers = $this->snapshotExistingCovers();

        if ($overwriteExisting) {
            $replacedBooks = $this->clearAvailableCatalog($warnings);
        }

        $placeholderSeq = $this->nextPlaceholderSequence();

        foreach ($entries as $entry) {
            $title = (string) $entry['excel_title'];
            $isbn = $this->normalizeIsbn((string) ($entry['isbn'] ?? ''));
            $category = $entry['category'] ?? null;

            if ($isbn === null) {
                if (isset($isbnByTitle[$title])) {
                    $isbn = $isbnByTitle[$title];
                } elseif ($lookupMissingIsbn) {
                    usleep(600000);
                    $lookup = $this->ndlLookup->lookupByTitle($title)
                        ?? $this->googleBooks->lookupByTitle($title);

                    if ($lookup !== null) {
                        $isbn = $lookup['isbn'];
                        $title = $lookup['title'] ?: $title;
                        $resolvedIsbn++;
                    }
                }
            }

            if ($isbn === null) {
                $isbn = $this->placeholderIsbn($placeholderSeq++);
                $placeholderIsbn++;
                $warnings[] = "ISBN未特定のため仮ISBNで登録: {$entry['excel_title']} ({$isbn})";
            }

            $isbnByTitle[(string) $entry['excel_title']] = $isbn;
            $importedIsbns[$isbn] = true;

            if ($category === null) {
                $category = $this->categoryGuesser->guess($title);
            }

            if (! array_key_exists($isbn, $coverCache)) {
                $coverCache[$isbn] = $this->coverForImportedBook(
                    $isbn,
                    $title,
                    (string) $entry['excel_title'],
                    $preservedCovers,
                    $this->normalizeImportedCover($entry['cover'] ?? null),
                );
            }

            $stock = max(0, (int) ($entry['stock_copies'] ?? $entry['available_copies'] ?? 1));
            $existingCount = Book::where('isbn', $isbn)->count();

            if ($overwriteExisting && $existingCount > 0) {
                $keptAttributes = [
                    'title' => $title,
                    'category' => $category,
                ];

                if ($coverCache[$isbn]) {
                    $keptAttributes['cover'] = $coverCache[$isbn];
                }

                Book::where('isbn', $isbn)->update($keptAttributes);
            }

            $copiesToCreate = $overwriteExisting
                ? max(0, $stock - $existingCount)
                : $stock;

            if ($stock <= 0 && $copiesToCreate === 0 && $existingCount === 0) {
                $skipped++;
                $warnings[] = "在庫0のため登録スキップ: {$entry['excel_title']}";

                continue;
            }

            if ($overwriteExisting && $stock < $existingCount) {
                $warnings[] = "「{$title}」は貸出中が {$existingCount} 冊あるため、Excelの在庫数 {$stock} 冊では減らせませんでした";
            }

            for ($copyIndex = 0; $copyIndex < $copiesToCreate; $copyIndex++) {
                Book::create([
                    'isbn' => $isbn,
                    'copy_number' => Book::nextCopyNumber($isbn),
                    'title' => $title,
                    'category' => $category,
                    'cover' => $coverCache[$isbn],
                    'status' => 'available',
                ]);

                $createdBooks++;
            }
        }

        if ($overwriteExisting) {
            $this->warnAboutBorrowedBooksNotInFile($importedIsbns, $warnings);
        }

        return [
            'created_books' => $createdBooks,
            'resolved_isbn' => $resolvedIsbn,
            'placeholder_isbn' => $placeholderIsbn,
            'skipped' => $skipped,
            'replaced_books' => $replacedBooks,
            'warnings' => $warnings,
        ];
    }

    /**
     * 上書き登録時に、貸出中でない冊だけ台帳から外す。
     * 貸出中の冊と未返却の貸出履歴は残す。
     *
     * @param  array<int, string>  $warnings
     */
    private function clearAvailableCatalog(array &$warnings): int
    {
        $borrowedBookIds = Loan::query()
            ->whereNull('returned_at')
            ->pluck('book_id');

        $booksToDelete = Book::query()
            ->when(
                $borrowedBookIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $borrowedBookIds),
            )
            ->get(['id']);

        if ($borrowedBookIds->isNotEmpty()) {
            $warnings[] = "貸出中 {$borrowedBookIds->count()} 冊の貸出状況はそのまま残しました";
        }

        if ($booksToDelete->isEmpty()) {
            return 0;
        }

        $bookIds = $booksToDelete->pluck('id');

        DB::transaction(function () use ($bookIds): void {
            Loan::whereIn('book_id', $bookIds)->delete();
            Book::whereIn('id', $bookIds)->delete();
        });

        return $booksToDelete->count();
    }

    /**
     * @param  array<string, true>  $importedIsbns
     * @param  array<int, string>  $warnings
     */
    private function warnAboutBorrowedBooksNotInFile(array $importedIsbns, array &$warnings): void
    {
        $kept = Book::query()
            ->whereHas('currentLoan')
            ->when(
                $importedIsbns !== [],
                fn ($query) => $query->whereNotIn('isbn', array_keys($importedIsbns)),
            )
            ->orderBy('title')
            ->get(['title', 'isbn']);

        foreach ($kept as $book) {
            $warnings[] = "Excelに無い貸出中資料を残しました: {$book->title}";
        }
    }

    private function normalizeIsbn(string $raw): ?string
    {
        $isbn = preg_replace('/[^0-9Xx]/', '', $raw) ?? '';

        if (strlen($isbn) === 13) {
            return $isbn;
        }

        if (strlen($isbn) === 10) {
            $core = '978'.substr($isbn, 0, 9);
            $sum = 0;

            foreach (str_split($core) as $index => $digit) {
                $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
            }

            $check = (10 - ($sum % 10)) % 10;

            return $core.$check;
        }

        return null;
    }

    private function nextPlaceholderSequence(): int
    {
        $maxIsbn = Book::where('isbn', 'like', '9789999%')->max('isbn');

        if (! is_string($maxIsbn) || strlen($maxIsbn) < 13) {
            return 1;
        }

        return ((int) substr($maxIsbn, 7, 5)) + 1;
    }

    private function placeholderIsbn(int $sequence): string
    {
        $base = '9789999'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($base) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        $check = (10 - ($sum % 10)) % 10;

        return $base.$check;
    }

    /**
     * @return array{by_isbn: array<string, string>, by_title: array<string, string>}
     */
    private function snapshotExistingCovers(): array
    {
        $byIsbn = [];
        $byTitle = [];

        $books = Book::query()
            ->whereNotNull('cover')
            ->where('cover', '!=', '')
            ->get(['isbn', 'title', 'cover']);

        foreach ($books as $book) {
            $cover = trim((string) $book->cover);

            if ($cover === '') {
                continue;
            }

            $isbn = trim((string) ($book->isbn ?? ''));

            if ($isbn !== '') {
                $byIsbn[$isbn] = $this->preferCover($byIsbn[$isbn] ?? null, $cover);
            }

            $titleKey = $this->normalizeTitle((string) $book->title);

            if ($titleKey !== '') {
                $byTitle[$titleKey] = $this->preferCover($byTitle[$titleKey] ?? null, $cover);
            }
        }

        return [
            'by_isbn' => $byIsbn,
            'by_title' => $byTitle,
        ];
    }

    /**
     * @param  array{by_isbn: array<string, string>, by_title: array<string, string>}  $preservedCovers
     */
    private function coverForImportedBook(
        string $isbn,
        string $title,
        string $excelTitle,
        array $preservedCovers,
        ?string $excelCover,
    ): ?string {
        if ($excelCover !== null) {
            return $this->remoteCoverStore->persist($excelCover);
        }

        $preserved = $preservedCovers['by_isbn'][$isbn]
            ?? $preservedCovers['by_title'][$this->normalizeTitle($title)]
            ?? $preservedCovers['by_title'][$this->normalizeTitle($excelTitle)]
            ?? null;

        if (is_string($preserved) && $preserved !== '') {
            return $preserved;
        }

        if (str_starts_with($isbn, '9789999')) {
            return null;
        }

        $lookedUp = $this->resolveCover($isbn);

        return $lookedUp ? $this->remoteCoverStore->persist($lookedUp) : null;
    }

    private function normalizeImportedCover(mixed $value): ?string
    {
        $cover = trim((string) $value);

        if ($cover === '') {
            return null;
        }

        if (BookCoverRules::isLocalPath($cover) || CoverSourceGuard::isSafeToFetch($cover)) {
            return $cover;
        }

        return null;
    }

    private function preferCover(?string $current, string $candidate): string
    {
        if ($current === null || $current === '') {
            return $candidate;
        }

        if (BookCoverRules::isLocalPath($current)) {
            return $current;
        }

        if (BookCoverRules::isLocalPath($candidate)) {
            return $candidate;
        }

        return $current;
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = preg_replace('/\s+/u', '', mb_strtolower(trim($title))) ?? '';

        return $normalized;
    }

    private function resolveCover(string $isbn): ?string
    {
        return $this->isbnLookup->lookup($isbn)['cover'] ?? null;
    }
}
