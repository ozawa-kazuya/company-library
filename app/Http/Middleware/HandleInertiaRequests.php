<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * ルートビュー（通常は app.blade.php）の定義
     */
    protected $rootView = 'app';

    /**
     * アセットのバージョン決定（キャッシュ対策）
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * ★最重要：Laravel から React (Inertia) 側へデータを共有する設定
     * ここに書いたデータだけが、Reactの usePage().props を通じて画面に届きます
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // ログイン中のユーザー情報を共有
            'auth' => [
                'user' => $request->user(),
            ],

            'mustChangePassword' => fn () => (bool) $request->user()?->usesInitialPassword(),

            // スキャン処理の結果（フラッシュメッセージ・選択されたユーザー情報）を
            // 途中で遮断させずに、React側へ「100%素通し」で共有する設定
            'flash' => [
                'success_message' => fn () => $request->session()->get('success_message'),
                'error_message' => fn () => $request->session()->get('error_message'),
                'import_warnings' => fn () => $request->session()->get('import_warnings'),
                'selected_user' => fn () => $request->session()->get('selected_user'),
                'clear_user' => fn () => $request->session()->get('clear_user'),
                'created_credentials' => fn () => $request->session()->get('created_credentials'),
                'reset_credential' => fn () => $request->session()->get('reset_credential'),
            ],
        ]);
    }
}
