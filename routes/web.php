<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\CoverImageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [BookController::class, 'index'])->name('dashboard');

    Route::get('/book-search', function () {
        return Inertia::render('BookSearch');
    })->name('books.searchView');

    Route::get('/borrow-scan', [BookController::class, 'borrowScan'])->name('books.borrowScan');
    Route::post('/books/borrow/exec', [BookController::class, 'borrowExec'])
        ->middleware('return.location:borrow')
        ->name('books.borrow.exec');
    Route::post('/books/{book}/return', [BookController::class, 'return'])
        ->middleware('return.location:return')
        ->name('books.return');

    Route::get('/password/change', [ProfileController::class, 'change'])->name('password.change');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/covers/remember', [CoverImageController::class, 'remember'])
        ->middleware('throttle:30,1')
        ->name('books.covers.remember');
    Route::get('/covers/{coverImage}', [CoverImageController::class, 'show'])->name('covers.show');
});

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/admin/menu', [BookController::class, 'adminMenu'])->name('admin.menu');
    Route::get('/admin/grafana', [BookController::class, 'adminGrafana'])->name('admin.grafana');
    Route::get('/admin/password', [ProfileController::class, 'adminEdit'])->name('admin.password');
    Route::get('/admin/overdue', [BookController::class, 'adminOverdue'])->name('admin.overdue');
    Route::get('/admin/loans', [BookController::class, 'adminLoanMonitor'])->name('admin.loans.monitor');
    Route::get('/admin/books/create', [BookController::class, 'adminBookCreate'])->name('admin.books.create');
    Route::get('/admin/books/import', [BookController::class, 'adminBookImport'])->name('admin.books.import');
    Route::post('/admin/books/import', [BookController::class, 'adminBookImportStore'])->name('admin.books.import.store');
    Route::get('/admin/books/export', [BookController::class, 'adminBookExport'])->name('admin.books.export');
    Route::get('/admin/books/lookup', [BookController::class, 'adminBookLookup'])->name('admin.books.lookup');
    Route::post('/admin/books/store', [BookController::class, 'adminBookStore'])->name('admin.books.store');
    Route::post('/admin/books/covers', [CoverImageController::class, 'store'])->name('admin.books.covers.store');
    Route::post('/admin/books/covers/from-url', [CoverImageController::class, 'storeFromUrl'])->name('admin.books.covers.from-url');
    Route::post('/admin/books/covers/backfill', [CoverImageController::class, 'backfillMissing'])->name('admin.books.covers.backfill');
    Route::get('/admin/books/edit', [BookController::class, 'adminBookEdit'])->name('admin.books.edit');
    Route::put('/admin/books/bulk-update', [BookController::class, 'adminBookBulkUpdate'])->name('admin.books.bulk-update');
    Route::delete('/admin/books/{book}', [BookController::class, 'adminBookDestroy'])->name('admin.books.destroy');
    Route::get('/users/create', [BookController::class, 'adminUserCreate'])->name('admin.users.create');
    Route::post('/users/store', [BookController::class, 'adminUserStore'])->name('admin.users.store');
    Route::get('/users/discard', [BookController::class, 'adminUserDiscardIndex'])->name('admin.users.discard');
    Route::post('/users/{id}/reset-password', [BookController::class, 'adminUserResetPassword'])->name('admin.users.reset-password');
    Route::delete('/users/discard/{id}', [BookController::class, 'adminUserDiscardDestroy'])->name('admin.users.discard.destroy');
    Route::get('/admin/search', [BookController::class, 'adminSearch'])->name('admin.search');
    Route::get('/admin/search/books', [BookController::class, 'adminSearchBooks'])->name('admin.search.books');
    Route::get('/admin/search/users', [BookController::class, 'adminSearchUsers'])->name('admin.search.users');
});

require __DIR__.'/auth.php';
