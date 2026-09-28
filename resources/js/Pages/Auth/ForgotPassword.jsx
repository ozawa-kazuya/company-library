import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col font-sans select-none antialiased text-gray-900">
            <Head title="パスワードを忘れた場合" />

            <div className="bg-[#0069e5] px-8 py-6 flex justify-between items-center shadow-md sticky top-0 z-50">
                <h1 className="text-white font-black tracking-wider text-lg">図書管理システム</h1>
            </div>

            <div className="flex-1 flex flex-col justify-center items-center px-4 py-12">
                <div className="w-full max-w-3xl bg-white border border-gray-100 rounded-lg shadow-sm overflow-hidden">
                    <div className="bg-gray-50/50 border-b border-gray-100 px-8 py-4">
                        <h2 className="text-gray-800 text-lg font-bold tracking-wide">パスワードを忘れた場合</h2>
                    </div>

                    <form onSubmit={submit} className="p-8 space-y-5">
                        <p className="text-sm text-gray-600 font-bold leading-relaxed">
                            登録しているメールアドレスを入力してください。再設定用のリンクを送信します。管理者アカウントはこの方法では再設定できません。
                        </p>

                        {status && (
                            <div className="bg-emerald-500 text-white p-3 rounded-md text-center font-bold text-sm">
                                {status}
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <label htmlFor="email" className="text-sm font-bold text-gray-700 block">
                                メールアドレス
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded-md px-4 py-2.5 text-base text-gray-800 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 focus:outline-none"
                                required
                                autoFocus
                            />
                            <InputError message={errors.email} className="text-xs font-bold" />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-[#0069e5] hover:bg-blue-600 text-white font-black py-2.5 rounded-md text-base shadow transition-all active:scale-[0.99] disabled:opacity-50 tracking-widest"
                        >
                            {processing ? '送信中…' : '再設定メールを送る'}
                        </button>

                        <Link
                            href={route('login')}
                            className="block text-center text-xs text-gray-400 hover:text-gray-600 underline tracking-wide"
                        >
                            ログイン画面へ戻る
                        </Link>
                    </form>
                </div>

                <div className="mt-12 text-center text-xs text-gray-400 font-bold tracking-wide">© 2026 株式会社エプコットソフトウェア</div>
            </div>
        </div>
    );
}
