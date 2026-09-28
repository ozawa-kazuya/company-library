<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return Redirect::route('admin.password');
        }

        return Redirect::route('password.change');
    }

    /**
     * 一般社員向けパスワード変更（初期パスワード強制変更でも使用）
     */
    public function change(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return Redirect::route('admin.password');
        }

        return Inertia::render('Auth/ChangePassword');
    }

    /**
     * 管理者向けパスワード変更画面（管理レイアウト内）
     */
    public function adminEdit(): Response
    {
        return Inertia::render('Admin/Password');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * アカウント削除は利用者除名（管理者）のみ。一般社員からの自己削除は不可。
     */
    public function destroy(): never
    {
        abort(403);
    }
}
