<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LoginFieldRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $fromInitial = $user->usesInitialPassword();
        $initial = (string) config('library.initial_password', 'password');

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'bail',
                Password::defaults(),
                'max:'.LoginFieldRules::PASSWORD_MAX,
                'regex:/^[a-zA-Z0-9]+$/',
                'confirmed',
                function (string $attribute, mixed $value, \Closure $fail) use ($initial): void {
                    if ($initial !== '' && hash_equals($initial, (string) $value)) {
                        $fail('初期パスワードは使用できません。別のパスワードを設定してください。');
                    }
                },
            ],
        ], LoginFieldRules::messages());

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        if ($fromInitial) {
            $home = $user->isAdmin() ? 'admin.menu' : 'dashboard';

            return redirect()->route($home)->with(
                'success_message',
                'パスワードを変更しました。',
            );
        }

        return back();
    }
}
