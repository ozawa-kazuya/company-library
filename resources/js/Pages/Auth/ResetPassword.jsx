import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import InputError from '@/Components/InputError';
import { loginInputClassName, passwordInputError } from '@/utils/loginFieldValidation';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email: email || '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        return () => reset('password', 'password_confirmation');
    }, []);

    const newPasswordError = passwordInputError(data.password) || errors.password;

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'));
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col font-sans antialiased text-gray-900">
            <Head title="パスワード再設定" />

            <div className="bg-[#0069e5] px-8 py-6 flex justify-between items-center shadow-md">
                <h1 className="text-white font-black tracking-wider text-lg">図書管理システム</h1>
            </div>

            <div className="flex-1 flex flex-col justify-center items-center px-4 py-12">
                <div className="w-full max-w-xl bg-white border border-gray-100 rounded-lg shadow-sm overflow-hidden">
                    <div className="bg-gray-50/50 border-b border-gray-100 px-8 py-4">
                        <h2 className="text-gray-800 text-lg font-bold tracking-wide">パスワード再設定</h2>
                    </div>

                    <form onSubmit={submit} className="p-8 space-y-5">
                        <p className="text-sm text-gray-600 font-bold leading-relaxed">
                            新しいパスワードを入力してください。
                        </p>

                        <div className="space-y-1.5">
                            <label htmlFor="email" className="text-sm font-bold text-gray-700 block">
                                メールアドレス
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className={loginInputClassName(false)}
                                required
                            />
                            <InputError message={errors.email} className="text-xs font-bold" />
                        </div>

                        <div className="space-y-1.5">
                            <label htmlFor="password" className="text-sm font-bold text-gray-700 block">
                                新しいパスワード
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                                className={loginInputClassName(Boolean(newPasswordError))}
                                required
                                autoFocus
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
                            {processing ? '設定中…' : 'パスワードを再設定する'}
                        </button>

                        <Link
                            href={route('login')}
                            className="block text-center text-xs text-gray-400 hover:text-gray-600 underline tracking-wide"
                        >
                            ログイン画面へ戻る
                        </Link>
                    </form>
                </div>
            </div>
        </div>
    );
}
