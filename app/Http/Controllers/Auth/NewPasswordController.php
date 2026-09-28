<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LoginFieldRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $initial = (string) config('library.initial_password', 'password');

        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'bail',
                'confirmed',
                Rules\Password::defaults(),
                'max:'.LoginFieldRules::PASSWORD_MAX,
                'regex:/^[a-zA-Z0-9]+$/',
                function (string $attribute, mixed $value, \Closure $fail) use ($initial): void {
                    if ($initial !== '' && hash_equals($initial, (string) $value)) {
                        $fail('初期パスワードは使用できません。別のパスワードを設定してください。');
                    }
                },
            ],
        ], LoginFieldRules::messages());

        $user = User::where('email', $request->email)->first();

        if ($user?->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => '管理者アカウントはメールでの再設定はできません。',
            ]);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'パスワードを再設定しました。新しいパスワードでログインしてください。');
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
