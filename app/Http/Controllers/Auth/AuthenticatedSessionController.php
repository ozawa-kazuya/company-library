<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * 🚪 ログイン画面（Login.jsx）を表示する
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * 🔑 ログインボタン押下時の実行処理（自動ルート分岐仕様）
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // 1. 社員番号とパスワードの認証を裏側で実行
        $request->authenticate();

        // 2. セッションの固定化攻撃を防ぐための安全な再生成（Breeze標準仕様）
        $request->session()->regenerate();

        // 3. 💡 【最重要大改造】ログインした社員の役職（role）をチェック！
        $user = Auth::user();

        if ($user?->usesInitialPassword()) {
            $request->session()->forget('url.intended');

            return redirect()->route($user->passwordChangeRouteName());
        }

        // ⚙️ 管理者はポータルメニューへ直行
        if ($user?->isAdmin()) {
            $request->session()->forget('url.intended');

            return redirect()->route('admin.menu');
        }

        // 👤 一般社員は本棚（マイページ）へ
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * 🚪 ログアウト実行処理
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
