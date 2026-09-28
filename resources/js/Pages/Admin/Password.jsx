import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import InputError from '@/Components/InputError';
import { loginInputClassName, passwordInputError } from '@/utils/loginFieldValidation';

export default function Password() {
    const mustChangePassword = Boolean(usePage().props.mustChangePassword);
    const passwordInput = useRef(null);
    const currentPasswordInput = useRef(null);

    const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const newPasswordError = passwordInputError(data.password) || errors.password;

    const submit = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (formErrors) => {
                if (formErrors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (formErrors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col font-sans antialiased text-gray-900">
            <Head title="パスワード変更" />

            <div className="bg-[#0069e5] px-8 py-6 flex justify-between items-center shadow-md">
                <h1 className="text-white font-black tracking-wider text-lg">図書管理システム</h1>
                <nav className="flex items-center gap-5 text-white text-sm font-bold">
                    {!mustChangePassword && (
                        <Link
                            href={route('admin.menu')}
                            className="hover:underline underline-offset-4"
                        >
                            ダッシュボード
                        </Link>
                    )}
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="hover:underline underline-offset-4"
                    >
                        ログアウト
                    </Link>
                </nav>
            </div>

            <div className="flex-1 flex flex-col justify-center items-center px-4 py-12">
                <div className="w-full max-w-xl bg-white border border-gray-100 rounded-lg shadow-sm overflow-hidden">
                    <div className="bg-gray-50/50 border-b border-gray-100 px-8 py-4">
                        <h2 className="text-gray-800 text-lg font-bold tracking-wide">パスワード変更</h2>
                    </div>

                    <form onSubmit={submit} className="p-8 space-y-5">
                        <p className="text-sm text-gray-600 font-bold leading-relaxed">
                            {mustChangePassword
                                ? '初期パスワードのままでは利用できません。新しいパスワードを設定してください。'
                                : '現在のパスワードと、新しいパスワードを入力してください。'}
                        </p>

                        {recentlySuccessful && (
                            <div className="bg-emerald-500 text-white p-3 rounded-md text-center font-bold text-sm">
                                パスワードを変更しました。
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <label htmlFor="current_password" className="text-sm font-bold text-gray-700 block">
                                現在のパスワード
                            </label>
                            <input
                                id="current_password"
                                ref={currentPasswordInput}
                                type="password"
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                autoComplete="current-password"
                                className={loginInputClassName(false)}
                                required
                            />
                            <InputError message={errors.current_password} className="text-xs font-bold" />
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="password" className="text-sm font-bold text-gray-700 block">
                                新しいパスワード
                            </label>
                            <input
                                id="password"
                                ref={passwordInput}
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                                className={loginInputClassName(Boolean(newPasswordError))}
                                required
                            />
                            <InputError message={newPasswordError} className="text-xs font-bold" />
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="password_confirmation" className="text-sm font-bold text-gray-700 block">
                                新しいパスワード（確認）
                            </label>
                            <input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                autoComplete="new-password"
                                className={loginInputClassName(false)}
                                required
                            />
                            <InputError message={errors.password_confirmation} className="text-xs font-bold" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-[#0069e5] hover:bg-blue-600 text-white font-black py-2.5 rounded-md text-base shadow transition-all active:scale-[0.99] disabled:opacity-50"
                        >
                            {processing ? '変更中…' : 'パスワードを変更する'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
