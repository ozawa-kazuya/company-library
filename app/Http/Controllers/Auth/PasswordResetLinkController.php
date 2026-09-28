<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user?->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => '管理者アカウントはメールでの再設定はできません。ログイン後のパスワード変更を利用してください。',
            ]);
        }

        if ($user) {
            Password::sendResetLink($validated);
        }

        return back()->with(
            'status',
            '入力されたメールアドレス宛に、再設定用のリンクを送信しました。メールをご確認ください。',
        );
    }
}
