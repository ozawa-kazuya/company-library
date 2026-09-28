<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\CoverImage;
use App\Services\CoverLookup;
use App\Services\RemoteCoverStore;
use App\Support\BookCoverRules;
use App\Support\CoverSourceGuard;
use Illuminate\Http\Request;

class CoverImageController extends Controller
{
    public function __construct(private RemoteCoverStore $remoteCoverStore) {}

    public function show(CoverImage $coverImage)
    {
        return response($coverImage->data, 200, [
            'Content-Type' => $coverImage->mime,
            'Content-Length' => (string) $coverImage->byte_size,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cover' => ['required', 'file', 'image', 'max:2048', 'mimes:jpeg,jpg,png,webp'],
        ], [
            'cover.required' => '表紙画像を選んでください。',
            'cover.image' => '表紙は画像ファイルを選んでください。',
            'cover.max' => '表紙画像は2MB以下にしてください。',
            'cover.mimes' => '表紙は JPEG / PNG / WebP で選んでください。',
        ]);

        $file = $request->file('cover');
        $contents = (string) file_get_contents($file->getRealPath());

        if (strlen($contents) < 32) {
            return response()->json([
                'message' => '画像が小さすぎます。別のファイルを選んでください。',
            ], 422);
        }

        $mime = $file->getMimeType() ?: 'image/jpeg';

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $image = CoverImage::create([
            'mime' => $mime,
            'byte_size' => strlen($contents),
            'data' => $contents,
        ]);

        return response()->json([
            'url' => '/covers/'.$image->id,
        ]);
    }

    public function remember(Request $request)
    {
        $validated = $request->validate([
            'isbn' => ['required', 'regex:/^\d{13}$/'],
            'url' => ['required', 'string', 'max:500'],
        ]);

        $url = trim($validated['url']);

        if (BookCoverRules::isLocalPath($url) || ! CoverSourceGuard::isAllowedUrl($url)) {
            return response()->json(['saved' => false], 422);
        }

        $books = Book::where('isbn', $validated['isbn'])->get();

        if ($books->isEmpty()) {
            return response()->json(['saved' => false], 404);
        }

        if ($books->every(fn (Book $book) => BookCoverRules::isLocalPath($book->cover))) {
            return response()->json(['saved' => false, 'url' => $books->first()->cover]);
        }

        $stored = $this->remoteCoverStore->persist($url) ?? $url;

        $updated = 0;

        foreach ($books as $book) {
            $current = trim((string) ($book->cover ?? ''));

            if ($current === '' || $current === $url) {
                $book->update(['cover' => $stored]);
                $updated++;
            }
        }

        return response()->json([
            'saved' => $updated > 0,
            'url' => $stored,
        ]);
    }

    public function backfillMissing(CoverLookup $lookup)
    {
        set_time_limit(0);

        $groups = Book::query()
            ->where(function ($query) {
                $query->whereNull('cover')->orWhere('cover', '');
            })
            ->orderBy('isbn')
            ->get()
            ->groupBy('isbn');

        $updated = 0;
        $failed = 0;

        foreach ($groups as $isbn => $books) {
            $cover = $lookup->resolve((string) $isbn, (string) ($books->first()->title ?? ''));

            if ($cover === null || $cover === '') {
                $failed += $books->count();

                continue;
            }

            $stored = $this->remoteCoverStore->persist($cover) ?? $cover;

            Book::where('isbn', $isbn)
                ->where(function ($query) {
                    $query->whereNull('cover')->orWhere('cover', '');
                })
                ->update(['cover' => $stored]);

            $updated += $books->count();
        }

        return redirect()->route('admin.books.edit')->with([
            'success_message' => "表紙のない資料を検索しました。更新 {$updated} 冊、見つからず {$failed} 冊。",
        ]);
    }

    public function storeFromUrl(Request $request)
    {
        $validated = $request->validate([
            'url' => array_merge(['required'], BookCoverRules::validationRules()),
        ], [
            'url.required' => '表紙のURLを入力してください。',
        ]);

        $url = trim((string) ($validated['url'] ?? ''));

        if ($url === '') {
            return response()->json([
                'message' => '表紙のURLを入力してください。',
            ], 422);
        }

        if (BookCoverRules::isLocalPath($url)) {
            return response()->json([
                'url' => $url,
                'saved' => true,
            ]);
        }

        if (! CoverSourceGuard::isSafeToFetch($url)) {
            return response()->json([
                'message' => 'このURLは使えません。',
            ], 422);
        }

        $stored = $this->remoteCoverStore->persist($url) ?? $url;

        return response()->json([
            'url' => $stored,
            'saved' => true,
        ]);
    }
}
