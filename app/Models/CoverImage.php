<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CoverImage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'mime',
        'byte_size',
        'data',
    ];

    protected $hidden = [
        'data',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $image): void {
            if (empty($image->id)) {
                $image->id = (string) Str::uuid();
            }
        });
    }
}
