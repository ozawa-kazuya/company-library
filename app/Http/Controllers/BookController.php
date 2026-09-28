<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use App\Services\BookInventoryExporter;
use App\Services\BookInventoryImporter;
use App\Services\BookCategoryGuesser;
use App\Services\BookIsbnLookup;
use App\Services\BookLoanStats;
use App\Services\BookSpreadsheetReader;
use App\Services\BookSpreadsheetWriter;
use App\Services\CoverLookup;
use App\Services\RemoteCoverStore;
use App\Services\ReturnLocationGuard;
use App\Services\UserCodeNormalizer;
use App\Support\BookCategories;
use App\Support\BookCoverRules;
use App\Support\InitialPasswordGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BookController extends Controller
{
    /**
     * 1. 一般社員用：本棚一覧 ＆ 返却用マイページ画面の表示
     */
    public function index(Request $request)
    {
        if (Auth::user()?->isAdmin()) {
            return redirect()->route('admin.menu');
        }

        $books = Book::with(['loans' => function ($query) {
            $query->whereNull('returned_at')->select('id', 'book_id');
        }])->latest()->get();

        // 自分が現在借りている履歴だけを取得します
        $myLoans = Loan::with('book')
            ->where('user_id', Auth::id())
            ->whereNull('returned_at')
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'books' => $books,
            'myLoans' => $myLoans,
            'returnLocation' => ReturnLocationGuard::status($request, 'return'),
            'borrowLocation' => ReturnLocationGuard::status($request, 'borrow'),
        ]);
    }

    /**
     * 2. 一般社員用：貸出スキャン画面（カメラ・手入力）の表示
     */
    public function borrowScan(Request $request)
    {
        $myBorrowedIsbns = Loan::with('book:id,isbn')
            ->where('user_id', Auth::id())
            ->whereNull('returned_at')
            ->get()
            ->pluck('book.isbn')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Inertia::render('BorrowScan', [
            'libraryLocation' => ReturnLocationGuard::status($request, 'borrow'),
            'myBorrowedIsbns' => $myBorrowedIsbns,
        ]);
    }

    /**
     * 3. 一般社員用：貸出実行
     */
    public function borrowExec(Request $request)
    {
        $cleanIsbn = preg_replace('/[-\s_＿]/', '', $request->input('isbn', ''));
        $request->merge(['isbn' => $cleanIsbn]);

        $request->validate([
            'isbn' => ['required', 'regex:/^\d{10,13}$/'],
            'duration' => 'required|integer|in:7,14,30',
        ], [
            'isbn.required' => 'ISBNコードを入力するか、バーコードをスキャンしてください。',
            'isbn.regex' => 'ISBNは10桁または13桁の数字で入力してください。',
        ]);

        if (! Book::where('isbn', $request->isbn)->exists()) {
            return redirect()->back()->with([
                'error_message' => '指定されたISBNの本が台帳に見つかりません。',
                'active_tab' => 'stock',
            ]);
        }

        $book = Book::findAvailableCopyByIsbn($request->isbn);

        if (! $book) {
            return redirect()->back()->with([
                'error_message' => 'この本は現在すべて貸出中です。返却をお待ちください。',
                'active_tab' => 'stock',
            ]);
        }

        $alreadyBorrowedSameTitle = Loan::query()
            ->where('user_id', Auth::id())
            ->whereNull('returned_at')
            ->whereHas('book', fn ($query) => $query->where('isbn', $request->isbn))
            ->exists();

        if ($alreadyBorrowedSameTitle) {
            return redirect()->back()->with([
                'error_message' => 'この本はすでに借用中です。返却してから再度お借りください。',
                'active_tab' => 'stock',
            ]);
        }

        $duration = (int) $request->duration;

        $dueDate = match ($duration) {
            7 => now()->addDays(7)->endOfDay(),
            30 => now()->addMonth()->endOfDay(),
            default => now()->addDays(14)->endOfDay(),
        };

        $durationLabel = match ($duration) {
            7 => '1週間',
            30 => '1ヶ月',
            default => '2週間',
        };

        Loan::create([
            'user_id' => Auth::id(),
            'book_id' => $book->id,
            'borrowed_at' => now(),
            'due_date' => $dueDate,
            'duration_days' => $duration,
        ]);

        $copyLabel = $book->copy_number > 1 ? "（第{$book->copy_number}冊）" : '';
        $dueText = $dueDate->format('Y-m-d');

        return redirect()->route('dashboard')->with([
            'success_message' => "「{$book->title}」{$copyLabel}を{$durationLabel}の期間で貸出しました。（返却期限: {$dueText}）",
            'active_tab' => 'stock',
        ]);
    }

    /**
     * 4. 一般社員用：返却処理の実行
     * 💡 ルーティングの指定に合わせ、メソッド名を「return」へ完全修正しました！
     */
    public function return(Request $request, $bookId)
    {
        // 自分が借りている該当の未返却データを検索
        $loan = Loan::where('book_id', $bookId)
            ->where('user_id', Auth::id())
            ->whereNull('returned_at')
            ->first();

        if (! $loan) {
            return redirect()->back()->with([
                'error_message' => '返却対象の貸出履歴が見つかりません。',
                'active_tab' => 'return',
            ]);
        }

        // 返却日時を記録して更新（返却完了状態へ）
        $loan->update([
            'returned_at' => now(),
        ]);

        return redirect()->route('dashboard')->with([
            'success_message' => '図書の返却処理が正常に完了しました。ありがとうございます！',
            'active_tab' => 'return',
        ]);
    }

    /**
     * ⚙️ 5. 管理者専用：新着書籍の登録画面の表示
     */
    public function adminBookCreate()
    {
        return Inertia::render('Admin/BookCreate');
    }

    /**
     * ⚙️ 管理者専用：Excel 入出力画面
     */
    public function adminBookImport()
    {
        return Inertia::render('Admin/BookImport');
    }

    /**
     * ⚙️ 管理者専用：Excel/CSV から書籍一括登録
     */
    public function adminBookImportStore(Request $request, BookSpreadsheetReader $reader, BookInventoryImporter $importer)
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('ファイルのアップロードに失敗しました。');

                        return;
                    }

                    $extension = strtolower($value->getClientOriginalExtension());

                    if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
                        $fail('CSV または XLSX 形式のファイルを選択してください。');
                    }
                },
            ],
            'lookup_missing_isbn' => ['sometimes', 'boolean'],
            'overwrite_existing' => ['sometimes', 'boolean'],
        ], [
            'file.required' => 'インポートするファイルを選択してください。',
            'file.max' => 'ファイルサイズは 10MB 以下にしてください。',
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile?->getRealPath();

        if (! is_string($path) || $path === '' || $uploadedFile === null) {
            return redirect()->back()->withErrors([
                'file' => 'ファイルの読み込みに失敗しました。',
            ]);
        }

        try {
            $entries = $reader->read($path, $uploadedFile->getClientOriginalExtension());
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors([
                'file' => 'ファイルの解析に失敗しました: '.$e->getMessage(),
            ]);
        }

        if ($entries === []) {
            return redirect()->back()->withErrors([
                'file' => '登録対象の書籍データが見つかりませんでした。ヘッダー行と題名列を確認してください。',
            ]);
        }

        $result = $importer->import(
            $entries,
            lookupMissingIsbn: $request->boolean('lookup_missing_isbn'),
            overwriteExisting: $request->boolean('overwrite_existing', false),
        );

        $finalCount = Book::count();

        $message = sprintf(
            '一括登録が完了しました。%d 行を処理し %d 冊を登録（スキップ %d 行、上書き削除 %d 冊）。台帳合計 %d 冊。',
            count($entries),
            $result['created_books'],
            $result['skipped'],
            $result['replaced_books'],
            $finalCount,
        );

        return redirect()->route('admin.books.import')->with([
            'success_message' => $message,
            'import_warnings' => array_slice($result['warnings'], 0, 20),
        ]);
    }

    /**
     * ⚙️ 管理者専用：登録済み書籍を Excel で保存
     */
    public function adminBookExport(BookInventoryExporter $exporter, BookSpreadsheetWriter $writer)
    {
        $path = $writer->write($exporter->sheets());
        $binary = (string) file_get_contents($path);
        @unlink($path);

        $filename = '社内図書台帳_'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.xlsx';

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    /**
     * ⚙️ 6. 管理者専用：ISBNから書籍情報を検索
     */
    public function adminBookLookup(Request $request)
    {
        $cleanIsbn = preg_replace('/[-\s_＿]/', '', $request->query('isbn', ''));

        $titleHint = trim((string) $request->query('title', ''));

        if (! preg_match('/^\d{13}$/', $cleanIsbn)) {
            return response()->json([
                'message' => 'ISBNは13桁の数字で入力してください。',
            ], 422);
        }

        $existingCopies = Book::where('isbn', $cleanIsbn)->count();
        $existingBook = Book::where('isbn', $cleanIsbn)->orderBy('copy_number')->first();

        if ($titleHint === '') {
            $titleHint = trim((string) ($existingBook?->title ?? ''));
        }

        $isPlaceholderIsbn = str_starts_with($cleanIsbn, '9789999');
        $found = $isPlaceholderIsbn ? null : app(BookIsbnLookup::class)->lookup($cleanIsbn);

        $title = $titleHint;
        $cover = null;

        if ($found !== null) {
            $foundTitle = trim((string) $found['title']);
            $title = $foundTitle !== '' ? $foundTitle : $titleHint;
            $cover = $found['cover'];
        }

        if ($cover === null) {
            $cover = app(CoverLookup::class)->resolve($cleanIsbn, $title);
        }

        $category = $this->lookupCategory($existingBook, $title);

        if ($title !== '') {
            return response()->json([
                'found' => true,
                'isbn' => $cleanIsbn,
                'title' => $title,
                'cover' => $cover,
                'category' => $category,
                'existingCopies' => $existingCopies,
            ]);
        }

        if ($existingBook) {
            return response()->json([
                'found' => true,
                'isbn' => $cleanIsbn,
                'title' => $existingBook->title,
                'cover' => $existingBook->cover ?: $cover,
                'category' => $category,
                'existingCopies' => $existingCopies,
            ]);
        }

        return response()->json([
            'message' => $cover
                ? 'タイトルが見つかりませんでした。入力するか、再取得できます。'
                : 'このISBNの書籍情報が見つかりませんでした。手動入力で登録できます。',
            'found' => false,
            'isbn' => $cleanIsbn,
            'cover' => $cover,
            'category' => $category,
        ], 404);
    }

    private function lookupCategory(?Book $existingBook, string $title): string
    {
        if (BookCategories::isValid($existingBook?->category)) {
            return $existingBook->category;
        }

        return app(BookCategoryGuesser::class)->guess($title);
    }

    /**
     * ⚙️ 7. 管理者専用：新着書籍の台帳追加登録の実行
     */
    public function adminBookStore(Request $request)
    {
        $cleanIsbn = preg_replace('/[-\s_＿]/', '', $request->input('isbn', ''));
        $request->merge(['isbn' => $cleanIsbn]);

        $validated = $request->validate([
            'isbn' => ['required', 'regex:/^\d{13}$/'],
            'title' => 'required|string|max:255',
            'category' => BookCategories::validationRules(),
            'cover' => BookCoverRules::validationRules(),
        ], [
            'isbn.required' => 'ISBNコードを入力してください。',
            'isbn.regex' => 'ISBNは13桁の数字で入力してください。',
            'title.required' => 'タイトルを入力してください。',
            'category.in' => 'カテゴリの指定が不正です。',
        ]);

        $copyNumber = Book::nextCopyNumber($validated['isbn']);
        $existingBook = Book::where('isbn', $validated['isbn'])->orderBy('copy_number')->first();
        $cover = filled($validated['cover'] ?? null)
            ? app(RemoteCoverStore::class)->persist($validated['cover'])
            : $existingBook?->cover;

        if (filled($validated['cover'] ?? null)) {
            Book::where('isbn', $validated['isbn'])->update([
                'cover' => $cover,
            ]);
        }

        Book::create([
            'isbn' => $validated['isbn'],
            'copy_number' => $copyNumber,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'cover' => $cover,
            'status' => 'available',
        ]);

        $copyLabel = $copyNumber > 1 ? "（第{$copyNumber}冊・在庫追加）" : '';

        return redirect()->route('admin.books.create')->with([
            'success_message' => "「{$validated['title']}」{$copyLabel}を正常に登録しました。",
        ]);
    }

    /**
     * ⚙️ 管理者専用：全資料編集画面
     */
    public function adminBookEdit()
    {
        $books = Book::with(['currentLoan.user'])
            ->orderBy('title')
            ->orderBy('isbn')
            ->orderBy('copy_number')
            ->get();

        $copyTotals = $books->groupBy('isbn')->map->count();

        $payload = $books->map(fn (Book $book) => [
            'id' => $book->id,
            'title' => $book->title,
            'isbn' => $book->isbn ?? '',
            'cover' => $book->cover ?? '',
            'copy_number' => $book->copy_number,
            'total_copies_in_group' => $copyTotals->get($book->isbn, 1),
            'category' => $book->category ?? BookCategories::default(),
            'is_borrowed' => $book->currentLoan !== null,
            'is_off_shelf' => $book->currentLoan === null && $book->status === 'rented',
            'borrower_name' => $book->currentLoan?->user?->name,
        ]);

        return Inertia::render('Admin/BookEdit', [
            'books' => $payload,
        ]);
    }

    /**
     * ⚙️ 管理者専用：全資料の一括更新
     */
    public function adminBookBulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'books' => ['required', 'array', 'min:1'],
            'books.*.id' => ['required', 'integer', 'exists:books,id'],
            'books.*.title' => ['required', 'string', 'max:255'],
            'books.*.isbn' => ['required', 'regex:/^\d{13}$/'],
            'books.*.category' => BookCategories::validationRules(),
            'books.*.cover' => BookCoverRules::validationRules(),
        ], [
            'books.*.title.required' => 'タイトルは必須です。',
            'books.*.isbn.regex' => 'ISBNは13桁の数字で入力してください。',
            'books.*.category.in' => 'カテゴリの指定が不正です。',
        ]);

        $updatedCount = 0;

        foreach ($validated['books'] as $bookData) {
            $isbn = preg_replace('/[^0-9]/', '', $bookData['isbn']);

            Book::where('id', $bookData['id'])->update([
                'title' => $bookData['title'],
                'isbn' => $isbn,
                'category' => $bookData['category'],
                'cover' => app(RemoteCoverStore::class)->persist($bookData['cover'] ?? null),
            ]);

            $updatedCount++;
        }

        return redirect()->route('admin.books.edit')->with([
            'success_message' => "資料 {$updatedCount} 冊の内容を更新しました。",
        ]);
    }

    /**
     * ⚙️ 管理者専用：資料廃棄（未貸出のみ）
     */
    public function adminBookDestroy(int $bookId)
    {
        $book = Book::with('currentLoan')->findOrFail($bookId);

        if ($book->currentLoan !== null) {
            return redirect()->back()->with([
                'error_message' => "「{$book->title}」は貸出中のため廃棄できません。",
            ]);
        }

        Loan::where('book_id', $book->id)->delete();
        $isbn = $book->isbn;
        $book->delete();

        if ($isbn) {
            Book::renumberCopies($isbn);
        }

        return redirect()->route('admin.books.edit')->with([
            'success_message' => '資料を廃棄し、台帳から外しました。',
        ]);
    }

    /**
     * ⚙️ 8. 管理者専用：ポータルメニュー画面の表示
     */
    public function adminMenu()
    {
        $overdueCount = Loan::query()->overdue()->count();

        return Inertia::render('Admin/Menu', [
            'overdueCount' => $overdueCount,
        ]);
    }

    public function adminGrafana(BookLoanStats $dashboard)
    {
        return Inertia::render('Admin/Grafana', $dashboard->payload());
    }

    /**
     * ⚙️ 9. 管理者専用：返却期限切れ一覧
     */
    public function adminOverdue()
    {
        $overdueLoans = Loan::query()
            ->overdue()
            ->with(['user', 'book'])
            ->orderBy('due_date')
            ->get();

        return Inertia::render('Admin/Overdue', [
            'overdueLoans' => $overdueLoans,
        ]);
    }

    /**
     * @deprecated admin.overdue へリダイレクト
     */
    public function adminLoanMonitor()
    {
        return redirect()->route('admin.overdue');
    }

    /**
     * ⚙️ 10. 管理者専用：利用者登録画面の表示
     */
    public function adminUserCreate()
    {
        return Inertia::render('Admin/UserCreate');
    }

    /**
     * ⚙️ 9. 管理者専用：5人同時利用者登録の実行
     */
    public function adminUserStore(Request $request)
    {
        $usersData = $request->input('users', []);

        $errorMessages = [];
        $pending = [];
        $seenCodes = [];
        $seenEmails = [];

        $normalizer = app(UserCodeNormalizer::class);

        foreach ($usersData as $index => $u) {
            $empId = isset($u['emp_id']) ? $normalizer->normalize(trim($u['emp_id'])) : '';
            $name = isset($u['name']) ? trim($u['name']) : '';
            $email = isset($u['email']) ? strtolower(trim($u['email'])) : '';

            if ($empId === '' && $name === '' && $email === '') {
                continue;
            }

            $rowNum = $index + 1;
            if ($empId === '' || $name === '' || $email === '') {
                $errorMessages[] = "{$rowNum}行目: 社員番号・氏名・メールアドレスをすべて入力してください。";

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessages[] = "{$rowNum}行目: メールアドレスの形式が正しくありません。";

                continue;
            }

            if (isset($seenCodes[$empId]) || isset($seenEmails[$email])) {
                $errorMessages[] = "{$rowNum}行目: 同じ入力の中で社員番号またはメールアドレスが重複しています。";

                continue;
            }

            $candidateCodes = $normalizer->resolveCandidates($empId);

            $isExist = User::where('email', $email)
                ->orWhereIn('user_code', $candidateCodes)
                ->exists();

            if ($isExist) {
                $errorMessages[] = "{$rowNum}行目: 社員番号 「{$empId}」 またはメールアドレス 「{$email}」 はすでに登録されています。";

                continue;
            }

            $seenCodes[$empId] = true;
            $seenEmails[$email] = true;
            $pending[] = [
                'user_code' => $empId,
                'name' => $name,
                'email' => $email,
            ];
        }

        if ($pending === [] && $errorMessages === []) {
            return redirect()->back()->withErrors(['users' => '登録する社員の情報を少なくとも1名分入力してください。']);
        }

        if ($errorMessages !== []) {
            return redirect()->back()->withErrors(['users' => implode(' ', $errorMessages)]);
        }

        $credentials = [];

        foreach ($pending as $row) {
            $plain = InitialPasswordGenerator::make();

            User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'user_code' => $row['user_code'],
                'password' => $plain,
                'must_change_password' => true,
                'role' => 'user',
            ]);

            $credentials[] = [
                'name' => $row['name'],
                'user_code' => $row['user_code'],
                'password' => $plain,
            ];
        }

        $registeredCount = count($credentials);

        return redirect()->route('admin.users.create')->with([
            'success_message' => "新入社員（合計 {$registeredCount} 名）をシステムへ同時に正常登録しました！",
            'created_credentials' => $credentials,
        ]);
    }

    /**
     * ⚙️ 10. 管理者専用：利用者除名画面（検索ベース）
     */
    public function adminUserDiscardIndex(Request $request)
    {
        $normalizer = app(UserCodeNormalizer::class);
        $query = trim($request->input('q', ''));

        $baseQuery = User::where('id', '!=', 1)->where('role', '!=', 'admin');

        $users = collect();
        if ($query !== '') {
            $candidates = $normalizer->resolveCandidates($query);
            $like = '%'.$query.'%';

            $users = (clone $baseQuery)
                ->with([
                    'loans' => function ($q) {
                        $q->whereNull('returned_at')->with('book:id,title,copy_number');
                    },
                ])
                ->where(function ($q) use ($like, $candidates) {
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);

                    if (! empty($candidates)) {
                        $q->orWhereIn('user_code', $candidates);
                        foreach ($candidates as $code) {
                            $q->orWhere('email', 'like', strtolower($code).'@%');
                        }
                    }
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'email', 'user_code']);
        }

        return Inertia::render('Admin/UserDiscard', [
            'users' => $users,
            'totalCount' => (clone $baseQuery)->count(),
            'filters' => ['q' => $query],
        ]);
    }

    /**
     * ⚙️ 11. 管理者専用：利用者の除名実行
     */
    public function adminUserDiscardDestroy($id)
    {
        $user = User::findOrFail($id);

        // 💡 鉄壁の安全装置：未返却の本がある社員は絶対に除名させないチェック
        $hasActiveLoan = Loan::where('user_id', $id)->whereNull('returned_at')->exists();
        if ($hasActiveLoan) {
            return redirect()->back()->with([
                'error_message' => "「{$user->name}」さんは現在図書を貸出中のため、除名処理はできません。返却を完了させてください。",
            ]);
        }

        // 過去の返却済み貸出履歴を綺麗に整理してから、ユーザーを名簿から抹消
        Loan::where('user_id', $id)->delete();
        $user->delete();

        return redirect()->back()->with([
            'success_message' => "社員「{$user->name}」さん（ID: {$id}）の除名（名簿抹消）処理が完了しました。",
        ]);
    }

    /**
     * 管理者専用：一般社員のパスワードを初期パスワードへ戻す
     */
    public function adminUserResetPassword($id)
    {
        $user = User::where('id', '!=', 1)
            ->where('role', '!=', 'admin')
            ->findOrFail($id);

        $plain = InitialPasswordGenerator::make();
        $user->update([
            'password' => $plain,
            'must_change_password' => true,
        ]);

        return redirect()->back()->with([
            'success_message' => "「{$user->name}」さんのパスワードを再発行しました。下に表示した仮パスワードを本人へ伝えてください。",
            'reset_credential' => [
                'name' => $user->name,
                'user_code' => $user->user_code,
                'password' => $plain,
            ],
        ]);
    }

    /**
     * ⚙️ 12. 管理者専用：旧横断検索 URL は資料検索へ
     */
    public function adminSearch()
    {
        return redirect()->route('admin.search.books');
    }

    /**
     * ⚙️ 管理者専用：資料検索
     */
    public function adminSearchBooks()
    {
        $books = Book::with([
            'loans' => function ($query) {
                $query->whereNull('returned_at');
            },
            'loans.user',
        ])->latest()->get();

        $borrowedCount = 0;
        $overdueCount = 0;

        foreach ($books as $book) {
            $loan = $book->loans->first();
            if (! $loan) {
                continue;
            }
            $borrowedCount++;
            if ($loan->due_date && $loan->due_date < now()) {
                $overdueCount++;
            }
        }

        return Inertia::render('Admin/Search', [
            'books' => $books,
            'stats' => [
                'total' => $books->count(),
                'borrowed' => $borrowedCount,
                'available' => $books->count() - $borrowedCount,
                'overdue' => $overdueCount,
            ],
        ]);
    }

    /**
     * ⚙️ 管理者専用：利用者検索
     */
    public function adminSearchUsers()
    {
        $users = User::where('role', 'user')
            ->with([
                'loans' => function ($query) {
                    $query->whereNull('returned_at')->with('book');
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'user_code']);

        return Inertia::render('Admin/UserSearch', [
            'users' => $users,
        ]);
    }
}
