<?php

namespace App\Services;

use App\Models\CoverImage;
use App\Support\BookCoverRules;
use App\Support\CoverSourceGuard;
use Illuminate\Support\Facades\Http;

class RemoteCoverStore
{
    public function persist(?string $cover): ?string
    {
        $cover = trim((string) $cover);

        if ($cover === '') {
            return null;
        }

        if (BookCoverRules::isLocalPath($cover)) {
            return $cover;
        }

        if (! CoverSourceGuard::isSafeToFetch($cover)) {
            return $cover;
        }

        return $this->download($cover) ?? $cover;
    }

    public function download(string $url): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'CompanyLibrary/1.0'])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contents = (string) $response->body();
        $mime = strtolower((string) $response->header('Content-Type'));

        if (strlen($contents) < 1000 || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        $image = CoverImage::create([
            'mime' => strtok($mime, ';') ?: 'image/jpeg',
            'byte_size' => strlen($contents),
            'data' => $contents,
        ]);

        return '/covers/'.$image->id;
    }
}
