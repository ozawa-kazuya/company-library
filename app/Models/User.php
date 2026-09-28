<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * 🔐 【最重要】一括保存（マスアサインメント）を許可するデータ列のリスト
     * ここに 'role' を追加したことで、Seederからの admin 権限が100%確実にMySQLへ書き込まれるようになります！
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * 配列やJSONに変換する際に隠す（非表示にする）デリケートなデータ列
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * データの型変換（キャスト）設定
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return strtolower((string) ($this->role ?? '')) === 'admin';
    }

    public function usesInitialPassword(): bool
    {
        return (bool) $this->must_change_password;
    }

    public function passwordChangeRouteName(): string
    {
        return $this->isAdmin() ? 'admin.password' : 'password.change';
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
