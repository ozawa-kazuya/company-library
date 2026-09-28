import { useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TemporaryPasswordPanel from '@/Components/TemporaryPasswordPanel';
import { getUserEmpId } from '@/utils/loanDisplay';

function UserSearchResult({ user, onDiscard, onResetPassword, processing }) {
    const activeLoans = user.loans || [];
    const hasActiveLoan = activeLoans.length > 0;

    return (
        <div className="px-5 py-4 space-y-3">
            <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3">
                <div>
                    <h4 className="font-black text-base text-gray-900">{user.name}</h4>
                    <p className="text-xs text-gray-400 font-mono font-bold mt-0.5">
                        {getUserEmpId(user)}
                    </p>
                </div>
                <span
                    className={`px-3 py-1 font-black text-xs rounded-lg shrink-0 border ${
                        hasActiveLoan
                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    }`}
                >
                    {hasActiveLoan ? `貸出中 ${activeLoans.length} 冊` : '貸出なし（除名可）'}
                </span>
            </div>

            {hasActiveLoan && (
                <ul className="space-y-2">
                    {activeLoans.map((loan) => (
                        <li
                            key={loan.id}
                            className="text-sm bg-amber-50 rounded-xl px-3 py-2 border border-amber-100 font-bold text-gray-700"
                        >
                            {loan.book?.title || '削除された書籍'}
                            {loan.book?.copy_number > 1 && (
                                <span className="text-gray-400 text-xs ml-1">
                                    第{loan.book.copy_number}冊
                                </span>
                            )}
                        </li>
                    ))}
                    <p className="text-xs text-amber-700 font-bold">
                        ※ 返却完了後に除名してください
                    </p>
                </ul>
            )}

            <div className="flex flex-col sm:flex-row justify-end gap-2 pt-1">
                <button
                    type="button"
                    onClick={() => onResetPassword(user.id, user.name)}
                    disabled={processing}
                    className="font-black text-xs px-5 py-2.5 bg-[#0069e5] border-[#0051b3] border-b-4 text-white hover:bg-[#005bcc] rounded-xl transition-all shadow-sm active:scale-[0.95] active:border-b-0 active:mt-1 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-[#0069e5]"
                >
                    パスワードを再発行
                </button>
                <button
                    type="button"
                    onClick={() => onDiscard(user.id, user.name)}
                    disabled={processing || hasActiveLoan}
                    className="font-black text-xs px-5 py-2.5 bg-[#b81c22] border-[#8a1217] border-b-4 text-white hover:bg-[#a1151a] rounded-xl transition-all shadow-sm active:scale-[0.95] active:border-b-0 active:mt-1 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-[#b81c22]"
                >
                    除名処分
                </button>
            </div>
        </div>
    );
}

export default function UserDiscard({ users = [], totalCount = 0, filters = {} }) {
    const flash = usePage().props.flash ?? {};
    const resetCredential = flash.reset_credential && flash.reset_credential.password
        ? [flash.reset_credential]
        : [];
    const searchForm = useForm({ q: filters.q ?? '' });
    const { delete: destroy, processing: discarding } = useForm();
    const { post: resetPassword, processing: resetting } = useForm();
    const processing = discarding || resetting;

    const handleSearch = (e) => {
        e.preventDefault();
        searchForm.get(route('admin.users.discard'), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClear = () => {
        searchForm.setData('q', '');
        searchForm.get(route('admin.users.discard'), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleResetPassword = (id, name) => {
        if (
            confirm(
                `「${name}」さんのパスワードを再発行しますか？\n新しい仮パスワードが画面に表示されます。`,
            )
        ) {
            resetPassword(route('admin.users.reset-password', id), { preserveScroll: true });
        }
    };

    const handleDiscardSubmit = (id, name) => {
        if (
            confirm(
                `⚠️ 本当に「${name}」さんを名簿データから削除し、除名しますか？\n（この処理は取り消せません）`,
            )
        ) {
            destroy(route('admin.users.discard.destroy', id), { preserveScroll: true });
        }
    };

    const hasSearched = (filters.q ?? '').trim() !== '';

    return (
        <AdminLayout title="利用者除名・パスワード再発行" maxWidth="max-w-4xl">
            <div className="space-y-6">
                <div className="flex justify-between items-center border-b border-gray-300 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl">🗑️</span>
                        <h2 className="font-black text-2xl text-gray-800 tracking-wide">
                            利用者除名・パスワード再発行
                        </h2>
                    </div>
                    <span className="px-3 py-1 bg-red-50 text-red-700 border border-red-200 font-black text-xs rounded-xl shadow-sm">
                        登録社員数: {totalCount} 名
                    </span>
                </div>

                {resetCredential.length > 0 && (
                    <TemporaryPasswordPanel
                        credentials={resetCredential}
                        title="パスワードを再発行しました。この画面を閉じると再表示できません。"
                        note="本人へ仮パスワードを伝え、次回ログイン時に変更するよう案内してください。"
                    />
                )}

                <div className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm space-y-4">
                    <p className="text-sm text-gray-600 font-bold">
                        社員番号または氏名で検索し、パスワードの再発行または除名を行ってください。一覧からの一括表示は行いません。
                    </p>
                    <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
                        <input
                            type="search"
                            value={searchForm.data.q}
                            onChange={(e) => searchForm.setData('q', e.target.value)}
                            placeholder="社員番号（001 / EMP-0001）または氏名"
                            className="flex-1 bg-gray-50 border border-gray-300 rounded-xl px-4 py-3 text-base text-gray-800 placeholder-gray-400 focus:border-gray-500 focus:ring-4 focus:ring-gray-100 focus:outline-none font-black"
                        />
                        <div className="flex gap-2 shrink-0">
                            <button
                                type="submit"
                                disabled={searchForm.processing || !searchForm.data.q.trim()}
                                className="px-6 py-3 bg-[#0069e5] hover:bg-[#005bcc] text-white font-black text-sm rounded-xl shadow-sm disabled:opacity-50 transition-all"
                            >
                                検索
                            </button>
                            {hasSearched && (
                                <button
                                    type="button"
                                    onClick={handleClear}
                                    disabled={searchForm.processing}
                                    className="px-4 py-3 bg-gray-200 hover:bg-gray-300 text-gray-600 font-black text-sm rounded-xl transition-all"
                                >
                                    クリア
                                </button>
                            )}
                        </div>
                    </form>
                </div>

                {!hasSearched ? (
                    <div className="text-center py-16 bg-white rounded-2xl border-4 border-dashed border-gray-200 px-6">
                        <span className="text-5xl block mb-4">🔍</span>
                        <h3 className="font-black text-gray-600 text-base">
                            対象の社員を検索してください
                        </h3>
                        <p className="text-xs text-gray-400 font-bold mt-2">
                            社員番号（001 形式）または氏名の一部を入力して「検索」を押してください
                        </p>
                    </div>
                ) : users.length === 0 ? (
                    <div className="text-center py-16 bg-white rounded-2xl border border-gray-200 px-6 shadow-sm">
                        <span className="text-5xl block mb-4">👤</span>
                        <h3 className="font-black text-gray-600 text-base">
                            「{filters.q}」に一致する社員は見つかりません
                        </h3>
                        <p className="text-xs text-gray-400 font-bold mt-2">
                            社員番号・氏名を確認して再度検索してください
                        </p>
                    </div>
                ) : (
                    <div className="bg-white border border-gray-200 rounded-2xl shadow-md overflow-hidden">
                        <div className="px-6 py-3.5 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                            <span className="text-xs font-black text-gray-500 uppercase tracking-wider">
                                検索結果
                            </span>
                            <span className="text-xs font-black text-gray-400">
                                {users.length} 名
                            </span>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {users.map((user) => (
                                <UserSearchResult
                                    key={user.id}
                                    user={user}
                                    onDiscard={handleDiscardSubmit}
                                    onResetPassword={handleResetPassword}
                                    processing={processing}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
