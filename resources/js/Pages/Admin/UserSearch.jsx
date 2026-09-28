import { useMemo, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import BookCoverImage from '@/Components/BookCoverImage';
import {
    getLoanDisplayInfo,
    getUserEmpId,
    matchesQuery,
} from '@/utils/loanDisplay';

function UserCard({ user }) {
    const activeLoans = user.loans || [];

    return (
        <div className="px-5 py-4 space-y-3">
            <div className="flex justify-between items-start gap-3">
                <div>
                    <h4 className="font-black text-base text-gray-900">{user.name}</h4>
                    <p className="text-xs text-gray-400 font-mono font-bold mt-0.5">
                        {getUserEmpId(user)}
                    </p>
                    {user.email && (
                        <p className="text-xs text-gray-400 font-bold mt-0.5">{user.email}</p>
                    )}
                </div>
                <span className="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 font-black text-xs rounded-lg shrink-0">
                    貸出 {activeLoans.length} 冊
                </span>
            </div>
            {activeLoans.length > 0 ? (
                <ul className="space-y-2">
                    {activeLoans.map((loan) => {
                        const loanInfo = getLoanDisplayInfo(loan);
                        return (
                            <li
                                key={loan.id}
                                className="flex items-center gap-3 text-sm bg-gray-50 rounded-xl px-3 py-2 border border-gray-100"
                            >
                                <BookCoverImage
                                    isbn={loan.book?.isbn}
                                    cover={loan.book?.cover}
                                    title={loan.book?.title}
                                    frameClassName="w-9 h-12 rounded-md shadow-sm border border-gray-200 overflow-hidden bg-gray-200 shrink-0"
                                />
                                <span className="font-bold text-gray-800 truncate flex-1 min-w-0">
                                    {loan.book?.title || '削除された書籍'}
                                    {loan.book?.copy_number > 1 && (
                                        <span className="text-gray-400 font-black text-xs ml-1">
                                            第{loan.book.copy_number}冊
                                        </span>
                                    )}
                                </span>
                                {loanInfo && (
                                    <span className="shrink-0 text-right">
                                        <span
                                            className={`inline-block px-2 py-0.5 text-[10px] font-black rounded-full border ${loanInfo.badgeColorClass}`}
                                        >
                                            {loanInfo.badgeText}
                                        </span>
                                        <span className="block text-[10px] font-bold text-gray-500 mt-0.5">
                                            貸出 {loanInfo.borrowedAtText}
                                        </span>
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <p className="text-xs text-gray-400 font-bold">現在、貸出中の資料はありません</p>
            )}
        </div>
    );
}

export default function UserSearch({ users = [] }) {
    const [searchQuery, setSearchQuery] = useState('');
    const cleanQuery = searchQuery.toLowerCase().trim();

    const filteredUsers = useMemo(() => {
        return (users || []).filter((user) => {
            if (!cleanQuery) {
                return true;
            }

            const empId = getUserEmpId(user).toLowerCase();
            return (
                matchesQuery(user.name, cleanQuery) ||
                matchesQuery(user.email, cleanQuery) ||
                empId.includes(cleanQuery)
            );
        });
    }, [users, cleanQuery]);

    return (
        <AdminLayout title="利用者検索" maxWidth="max-w-4xl">
            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b border-gray-200 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl" aria-hidden="true">
                            👤
                        </span>
                        <h2 className="font-black text-2xl text-gray-800 tracking-wide">
                            利用者検索
                        </h2>
                    </div>
                    <span className="text-xs font-black text-gray-500 bg-white border border-gray-200 px-3 py-1.5 rounded-xl shadow-sm">
                        {filteredUsers.length} 名
                    </span>
                </div>

                <div
                    className="sticky z-40 -mx-4 px-4 py-2 bg-gray-100"
                    style={{ top: 'var(--admin-header-h, 4.5rem)' }}
                >
                    <div className="bg-white border border-gray-200 rounded-3xl p-5 shadow-md">
                        <div className="relative">
                            <label htmlFor="admin-user-search" className="sr-only">
                                利用者検索
                            </label>
                            <input
                                id="admin-user-search"
                                type="search"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="氏名、社員番号、メールで検索..."
                                className="w-full bg-gray-50 border border-gray-300 rounded-2xl px-5 py-4 text-base text-gray-800 placeholder-gray-400 focus:border-gray-500 focus:ring-4 focus:ring-gray-100 focus:outline-none shadow-inner transition-all font-black"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={() => setSearchQuery('')}
                                    className="absolute right-4 top-4 text-xs font-black bg-gray-200 hover:bg-gray-300 text-gray-500 px-3 py-1.5 rounded-full transition-all"
                                >
                                    クリア
                                </button>
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h3 className="font-black text-sm text-gray-700">利用者一覧</h3>
                        <span className="text-xs font-black text-gray-400">
                            {filteredUsers.length} 名
                        </span>
                    </div>

                    <div className="divide-y divide-gray-100">
                        {filteredUsers.length === 0 ? (
                            <div className="text-center py-16 px-6">
                                <p className="font-black text-gray-500">該当する利用者が見つかりません</p>
                                <p className="text-xs text-gray-400 font-bold mt-2">
                                    検索条件を変更してください
                                </p>
                            </div>
                        ) : (
                            filteredUsers.map((user) => <UserCard key={user.id} user={user} />)
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
