<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'isbn',
        'copy_number',
        'category',
        'cover',
        'status', // 'available' または 'rented'
    ];

    /**
     * 同一ISBNの次の冊番号を返す
     */
    public static function nextCopyNumber(string $isbn): int
    {
        $max = static::where('isbn', $isbn)->max('copy_number');

        return ($max ?? 0) + 1;
    }

    /**
     * 同一ISBNの冊番号を 1 から連番で振り直す（削除後など）
     */
    public static function renumberCopies(?string $isbn): void
    {
        if (empty($isbn)) {
            return;
        }

        $books = static::where('isbn', $isbn)
            ->orderBy('copy_number')
            ->orderBy('id')
            ->get();

        foreach ($books as $index => $book) {
            $newNumber = $index + 1;
            if ((int) $book->copy_number !== $newNumber) {
                $book->update(['copy_number' => $newNumber]);
            }
        }
    }

    /**
     * 未貸出の1冊をISBNから取得する
     */
    public static function findAvailableCopyByIsbn(string $isbn): ?self
    {
        return static::where('isbn', $isbn)
            ->where('status', 'available')
            ->whereDoesntHave('currentLoan')
            ->orderBy('copy_number')
            ->first();
    }

    /**
     * 1冊の本は複数の貸出履歴を持つ（過去の全履歴）
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * ★最重要：この本の「現在の貸出情報」を1件だけ取得する
     * returned_at が NULL（未返却）のレコードを紐付けます
     */
    public function currentLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->whereNull('returned_at');
    }
}
