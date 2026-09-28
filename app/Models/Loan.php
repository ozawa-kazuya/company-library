<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'borrowed_at',
        'due_date',
        'duration_days',
        'returned_at',
        'slack_notified_at',
    ];

    protected $casts = [
        'borrowed_at' => 'datetime',
        'due_date' => 'datetime',
        'returned_at' => 'datetime',
        'slack_notified_at' => 'datetime',
    ];

    /**
     * この貸出履歴は特定の社員（User）に属する
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この貸出履歴は特定の書籍（Book）に属する
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function scopeOverdue($query)
    {
        return $query
            ->whereNull('returned_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }
}
