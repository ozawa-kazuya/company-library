<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\UserCodeNormalizer;
use App\Support\LoginFieldRules;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * 認可の判定（常にtrue）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 🔍 ★バリデーション：メールアドレスの代わりに「user_code（社員番号）」を必須にします
     */
    public function rules(): array
    {
        return [
            'user_code' => LoginFieldRules::userCode(),
            'password' => LoginFieldRules::password(),
        ];
    }

    /**
     * エラーメッセージの日本語化
     */
    public function messages(): array
    {
        return LoginFieldRules::messages();
    }

    /**
     * 🔑 ★ログイン試行処理のカスタマイズ
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $normalizer = app(UserCodeNormalizer::class);
        $candidates = $normalizer->resolveCandidates($this->input('user_code', ''));
        $password = $this->input('password');

        $user = User::query()
            ->whereIn('user_code', $candidates)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'user_code' => __('auth.failed'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * 連続ログイン失敗時のロックアウト制限設定
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'user_code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * トロトルキーの生成
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('user_code')).'|'.$this->ip());
    }
}
