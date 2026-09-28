import { useMemo, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import BookCoverImage from '@/Components/BookCoverImage';
import {
    getCurrentLoan,
    getLoanDisplayInfo,
    getUserEmpId,
    matchesQuery,
} from '@/utils/loanDisplay';
import { formatCopyLabel } from '@/utils/bookGrouping';
import { BOOK_CATEGORY_FILTER_OPTIONS } from '@/constants/bookCategories';

const STATUS_FILTERS = [
    { id: 'all', label: 'すべて' },
    { id: 'available', label: '保管中' },
    { id: 'borrowed', label: '貸出中' },
    { id: 'overdue', label: '期限切れ' },
];

function BookRow({ book }) {
    const currentLoan = getCurrentLoan(book);
    const isBorrowed = !!currentLoan;
    const loanInfo = isBorrowed ? getLoanDisplayInfo(currentLoan) : null;
    const borrowerName = currentLoan?.user?.name;
    const copyLabel = formatCopyLabel(book.copy_number, book.totalCopiesInGroup ?? 1);

    return (
        <>
            {/* Desktop row */}
            <div className="hidden md:grid md:grid-cols-12 px-6 py-6 items-center hover:bg-gray-50/40 transition-all">
                <div className="col-span-4 pr-4 flex items-center gap-3 min-w-0">
                    <BookCoverImage
                        isbn={book.isbn}
                        cover={book.cover}
                        title={book.title}
                        frameClassName="w-12 h-[68px] rounded-md shadow-sm border border-gray-200 overflow-hidden bg-gray-200 shrink-0"
                    />
                    <div className="min-w-0 space-y-0.5">
                        <h4 className="font-black text-base text-gray-900 truncate">{book.title}</h4>
                        <p className="text-xs text-gray-400 font-bold font-mono">
                            ISBN: {book.isbn || '—'}
                            {copyLabel && (
                                <span className="ml-2 text-[#00a0e9]">{copyLabel}</span>
                            )}
                        </p>
                    </div>
                </div>

                <div className="col-span-2 pl-2">
                    <span className="px-3 py-1 bg-gray-100 text-gray-600 font-black text-xs rounded-lg border border-gray-200/60">
                        {book.category || '未分類'}
                    </span>
                </div>

                <div className="col-span-3 flex flex-col items-start justify-center space-y-1.5 pl-6">
                    {isBorrowed ? (
                        <div className="space-y-1">
                            <span className="inline-block px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 font-black text-[11px] rounded-md shadow-sm">
                                貸出中
                            </span>
                            <p className="font-black text-sm text-gray-800">
                                借用者:{' '}
                                <span className="text-[#00a0e9]">{borrowerName || '不明'}</span>
                            </p>
                            {currentLoan?.user && (
                                <p className="text-[10px] text-gray-400 font-bold">
                                    {getUserEmpId(currentLoan.user)}
                                </p>
                            )}
                        </div>
                    ) : (
                        <span className="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 font-black text-xs rounded-lg shadow-sm">
                            保管中（貸出可）
                        </span>
                    )}
                </div>

                <div className="col-span-3 flex flex-col items-end justify-center space-y-1.5 pr-4">
                    {loanInfo ? (
                        <div className="text-right flex flex-col items-end space-y-1">
                            <span
                                className={`px-5 py-1.5 border rounded-full font-black text-sm shadow-inner ${loanInfo.badgeColorClass}`}
                            >
                                {loanInfo.badgeText}
                            </span>
                            <div className="text-right font-black text-sm text-gray-500 space-y-0.5 mt-0.5">
                                <div className="flex items-center justify-end space-x-1 text-gray-700 text-base">
                                    <span aria-hidden="true">🗓️</span>
                                    <span>貸出: {loanInfo.borrowedAtText}</span>
                                </div>
                                <div className="flex items-center justify-end space-x-1 text-gray-700 text-base">
                                    <span>予定: {loanInfo.finalDueDateText}</span>
                                </div>
                                <div className="text-xs text-gray-400 font-bold">
                                    {loanInfo.durationLabel}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <span className="text-xs text-gray-300 font-bold tracking-wider pr-4">—</span>
                    )}
                </div>
            </div>

            {/* Mobile card */}
            <div className="md:hidden px-4 py-4 space-y-3 hover:bg-gray-50/40 transition-all">
                <div className="flex items-start gap-3">
                    <BookCoverImage
                        isbn={book.isbn}
                        cover={book.cover}
                        title={book.title}
                        frameClassName="w-12 h-[68px] rounded-md shadow-sm border border-gray-200 overflow-hidden bg-gray-200 shrink-0"
                    />
                    <div className="min-w-0">
                        <h4 className="font-black text-base text-gray-900">{book.title}</h4>
                        <p className="text-xs text-gray-400 font-bold font-mono mt-0.5">
                            ISBN: {book.isbn || '—'}
                            {copyLabel && (
                                <span className="ml-2 text-[#00a0e9]">{copyLabel}</span>
                            )}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <span className="px-3 py-1 bg-gray-100 text-gray-600 font-black text-xs rounded-lg border border-gray-200/60">
                        {book.category || '未分類'}
                    </span>
                    {isBorrowed ? (
                        <span className="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 font-black text-xs rounded-lg">
                            貸出中: {borrowerName || '不明'}
                        </span>
                    ) : (
                        <span className="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 font-black text-xs rounded-lg">
                            保管中
                        </span>
                    )}
                    {loanInfo?.isOverdue && (
                        <span className="px-3 py-1 bg-red-100 text-red-600 border border-red-300 font-black text-xs rounded-lg animate-pulse">
                            期限切れ
                        </span>
                    )}
                </div>
                {loanInfo && (
                    <p className="text-xs font-bold text-gray-600">
                        貸出: {loanInfo.borrowedAtText}　予定: {loanInfo.finalDueDateText}
                        <span className="text-gray-400 ml-1">{loanInfo.durationLabel}</span>
                    </p>
                )}
            </div>
        </>
    );
}

export default function Search({ books = [], stats = {} }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [categoryFilter, setCategoryFilter] = useState('すべて');

    const cleanQuery = searchQuery.toLowerCase().trim();

    const filteredBooks = useMemo(() => {
        const isbnCounts = (books || []).reduce((acc, book) => {
            if (book.isbn) {
                acc[book.isbn] = (acc[book.isbn] || 0) + 1;
            }
            return acc;
        }, {});

        const booksWithGroupInfo = (books || []).map((book) => ({
            ...book,
            totalCopiesInGroup: book.isbn ? isbnCounts[book.isbn] : 1,
        }));

        return booksWithGroupInfo.filter((book) => {
            const currentLoan = getCurrentLoan(book);
            const loanInfo = currentLoan ? getLoanDisplayInfo(currentLoan) : null;
            const borrowerName = currentLoan?.user?.name || '';
            const borrowerEmpId = currentLoan?.user ? getUserEmpId(currentLoan.user) : '';

            if (categoryFilter !== 'すべて' && book.category !== categoryFilter) {
                return false;
            }

            if (statusFilter === 'available' && currentLoan) {
                return false;
            }
            if (statusFilter === 'borrowed' && !currentLoan) {
                return false;
            }
            if (statusFilter === 'overdue' && !loanInfo?.isOverdue) {
                return false;
            }

            if (!cleanQuery) {
                return true;
            }

            return (
                matchesQuery(book.title, cleanQuery) ||
                (book.isbn && book.isbn.includes(cleanQuery)) ||
                matchesQuery(borrowerName, cleanQuery) ||
                matchesQuery(borrowerEmpId, cleanQuery)
            );
        });
    }, [books, cleanQuery, statusFilter, categoryFilter]);

    const {
        total = 0,
        borrowed = 0,
        available = 0,
        overdue = 0,
    } = stats;

    return (
        <AdminLayout title="資料検索" maxWidth="max-w-6xl">
            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b border-gray-200 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl" aria-hidden="true">
                            📚
                        </span>
                        <h2 className="font-black text-2xl text-gray-800 tracking-wide">
                            資料検索
                        </h2>
                    </div>
                    <span className="text-xs font-black text-gray-500 bg-white border border-gray-200 px-3 py-1.5 rounded-xl shadow-sm">
                        全 {total} 冊 / 貸出中 {borrowed} / 期限切れ {overdue}
                    </span>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { label: '総資料数', value: total, color: 'text-gray-800' },
                        { label: '保管中', value: available, color: 'text-emerald-600' },
                        { label: '貸出中', value: borrowed, color: 'text-amber-600' },
                        { label: '期限切れ', value: overdue, color: 'text-red-600' },
                    ].map((item) => (
                        <div
                            key={item.label}
                            className="bg-white border border-gray-200 rounded-2xl p-4 text-center shadow-sm"
                        >
                            <p className={`text-2xl font-black ${item.color}`}>{item.value}</p>
                            <p className="text-xs text-gray-400 font-bold mt-1">{item.label}</p>
                        </div>
                    ))}
                </div>

                <div
                    className="sticky z-40 -mx-4 px-4 py-2 bg-gray-100"
                    style={{ top: 'var(--admin-header-h, 4.5rem)' }}
                >
                    <div className="bg-white border border-gray-200 rounded-3xl p-5 shadow-md space-y-4">
                    <div className="relative">
                        <label htmlFor="admin-book-search" className="sr-only">
                            資料検索
                        </label>
                        <input
                            id="admin-book-search"
                            type="search"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="タイトル、ISBN、借用者名、社員番号で検索..."
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

                    <div className="flex flex-col sm:flex-row gap-3">
                        <div className="flex flex-wrap gap-2">
                            {STATUS_FILTERS.map((filter) => (
                                <button
                                    key={filter.id}
                                    type="button"
                                    onClick={() => setStatusFilter(filter.id)}
                                    className={`px-3 py-1.5 rounded-xl text-xs font-black border transition-all ${
                                        statusFilter === filter.id
                                            ? 'bg-gray-800 border-gray-800 text-white'
                                            : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'
                                    }`}
                                >
                                    {filter.label}
                                </button>
                            ))}
                        </div>
                        <select
                            value={categoryFilter}
                            onChange={(e) => setCategoryFilter(e.target.value)}
                            className="sm:ml-auto bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-black text-gray-700 focus:outline-none focus:border-gray-500"
                        >
                            {BOOK_CATEGORY_FILTER_OPTIONS.map((cat) => (
                                <option key={cat} value={cat}>
                                    {cat === 'すべて' ? 'カテゴリ: すべて' : cat}
                                </option>
                            ))}
                        </select>
                    </div>
                    </div>
                </div>

                <div className="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden">
                    <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h3 className="font-black text-sm text-gray-700">資料一覧</h3>
                        <span className="text-xs font-black text-gray-400">
                            {filteredBooks.length} 件
                        </span>
                    </div>

                    <div className="hidden md:grid md:grid-cols-12 bg-gray-50/80 px-6 py-3 border-b border-gray-200 text-xs font-black text-gray-400 uppercase tracking-wider">
                        <div className="col-span-4 pl-1">書籍情報</div>
                        <div className="col-span-2 pl-2">ジャンル</div>
                        <div className="col-span-3 pl-6">貸出状況</div>
                        <div className="col-span-3 text-right pr-4">期限</div>
                    </div>

                    <div className="divide-y divide-gray-100">
                        {filteredBooks.length === 0 ? (
                            <div className="text-center py-16 px-6">
                                <p className="font-black text-gray-500">該当する資料が見つかりません</p>
                                <p className="text-xs text-gray-400 font-bold mt-2">
                                    検索条件やフィルターを変更してください
                                </p>
                            </div>
                        ) : (
                            filteredBooks.map((book) => <BookRow key={book.id} book={book} />)
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
