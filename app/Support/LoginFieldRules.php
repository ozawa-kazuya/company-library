<?php

namespace App\Support;

final class LoginFieldRules
{
    public const USER_CODE_MAX = 3;

    public const PASSWORD_MAX = 10;

    public static function userCode(): array
    {
        return ['required', 'string', 'bail', 'max:'.self::USER_CODE_MAX, 'regex:/^[0-9]+$/'];
    }

    public static function password(): array
    {
        return ['required', 'string', 'bail', 'max:'.self::PASSWORD_MAX, 'regex:/^[a-zA-Z0-9]+$/'];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'user_code.required' => '社員番号は必須入力です。',
            'user_code.max' => '社員番号は3桁以内で入力してください。',
            'user_code.regex' => '社員番号は半角数字で入力してください。',
            'password.required' => 'パスワードは必須入力です。',
            'password.max' => 'パスワードは10文字以内で入力してください。',
            'password.regex' => 'パスワードは半角英数字で入力してください。',
        ];
    }
}
