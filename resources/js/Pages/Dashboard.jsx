import { useState, useCallback } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { getLoanDisplayInfo } from '@/utils/loanDisplay';
import { groupBooksByIsbn, formatStockLabel } from '@/utils/bookGrouping';
import { BOOK_CATEGORY_FILTER_OPTIONS } from '@/constants/bookCategories';
import BookCoverImage from '@/Components/BookCoverImage';
import BookShelfDetailModal from '@/Components/BookShelfDetailModal';
import FlashBanner from '@/Components/FlashBanner';
import { getBookKey, collectBorrowedIsbns, isAlreadyBorrowedByUser } from '@/utils/bookShelfDetail';

export default function Dashboard({ auth, books = [], myLoans = [], returnLocation = {}, borrowLocation = {} }) {
    const { post, processing } = useForm();

    const returnRestricted = returnLocation?.restricted ?? false;
    const canReturnHere = returnLocation?.allowedHere ?? true;
    const borrowRestricted = borrowLocation?.restricted ?? false;
    const canBorrowHere = borrowLocation?.allowedHere ?? true;
    const returnBlockedMessage =
        returnLocation?.message ??
        '返却は社内ネットワークからのみ可能です。オフィスに戻ってから返却してください。';

    // 🔍 検索キーワードと選択カテゴリの状態管理
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('すべて');
    const [showReturnPanel, setShowReturnPanel] = useState(false);
    const [selectedBook, setSelectedBook] = useState(null);
    const [pullingBookKey, setPullingBookKey] = useState(null);
    const [showBookModal, setShowBookModal] = useState(false);

    const handleBookClick = useCallback((book) => {
        const key = getBookKey(book);
        setPullingBookKey(key);
        window.setTimeout(() => {
            setSelectedBook(book);
            setShowBookModal(true);
        }, 280);
    }, []);

    const handleCloseBookModal = useCallback(() => {
        setShowBookModal(false);
        window.setTimeout(() => {
            setSelectedBook(null);
            setPullingBookKey(null);
        }, 220);
    }, []);

    const handleReturnSubmit = (bookId, bookTitle) => {
        if (returnRestricted && !canReturnHere) {
            alert(returnBlockedMessage);
            return;
        }

        if (confirm(`「${bookTitle}」を返却しますか？`)) {
            post(route('books.return', bookId), { preserveScroll: true });
        }
    };

    const safeBooks = books || [];
    const safeMyLoans = myLoans || [];
    const myBorrowedIsbns = collectBorrowedIsbns(safeMyLoans);
    const groupedBooks = groupBooksByIsbn(safeBooks);

    // 💡 Reactのメモリ上でキーワードとカテゴリをリアルタイムに絞り込む数式
    const filteredBooks = groupedBooks.filter((book) => {
        const matchesCategory = selectedCategory === 'すべて' || book.category === selectedCategory;
        const cleanQuery = searchQuery.toLowerCase().trim();
        const matchesQuery = !cleanQuery || 
            (book.title && book.title.toLowerCase().includes(cleanQuery)) ||
            (book.isbn && book.isbn.includes(cleanQuery));

        return matchesCategory && matchesQuery;
    });

    const categories = BOOK_CATEGORY_FILTER_OPTIONS;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-black text-2xl text-gray-800 leading-tight">社内図書マイページ</h2>}
        >
            <Head title="図書管理ホーム" />

            {/* スマホは従来幅、PCはラックを横に広げる */}
            <div className="py-6 md:py-8 px-3 max-w-xl md:max-w-6xl lg:max-w-7xl mx-auto w-full font-sans select-none antialiased text-gray-900">
                
                <div className="md:max-w-xl md:mx-auto">
                <FlashBanner />

                {/* 借りる・返却 */}
                <div className="bg-white border border-gray-200 rounded-3xl p-4 md:p-5 shadow-sm space-y-4 mb-5">
                    <div className="flex gap-2">
                        <Link
                            href={route('books.borrowScan')}
                            className={`flex-1 text-center font-black text-sm py-3.5 rounded-xl shadow transition-all active:scale-[0.98] ${
                                borrowRestricted && !canBorrowHere
                                    ? 'bg-gray-400 border-b-4 border-gray-500 text-white/90 pointer-events-none cursor-not-allowed'
                                    : 'text-white bg-[#00a0e9] border-b-4 border-[#007bbf]'
                            }`}
                            aria-disabled={borrowRestricted && !canBorrowHere}
                            title={
                                borrowRestricted && !canBorrowHere
                                    ? borrowLocation?.message
                                    : undefined
                            }
                        >
                            📖 本を借りる
                        </Link>
                        <button
                            type="button"
                            onClick={() => setShowReturnPanel((prev) => !prev)}
                            className={`flex-1 text-center font-black text-sm py-3.5 rounded-xl shadow transition-all active:scale-[0.98] ${
                                showReturnPanel
                                    ? 'text-white bg-[#2b2b2b] border-b-4 border-black ring-2 ring-gray-300'
                                    : 'text-white bg-[#434343] border-b-4 border-[#2b2b2b]'
                            }`}
                        >
                            🔄 本を返す
                            <span className="ml-1 text-xs opacity-80">({safeMyLoans.length})</span>
                        </button>
                    </div>

                    {borrowRestricted && !canBorrowHere && (
                        <div className="bg-amber-50 border-2 border-amber-200 text-amber-900 p-3 rounded-xl text-xs font-bold leading-relaxed space-y-1">
                            <p className="font-black">📡 貸出場所の制限</p>
                            <p>{borrowLocation?.message}</p>
                            {borrowLocation?.showDebug && (
                                <p className="text-amber-800/90">
                                    接続元 IP:{' '}
                                    <span className="font-mono">{borrowLocation?.currentIp ?? '—'}</span>
                                    {' · '}
                                    判定:{' '}
                                    <span className="font-mono">{borrowLocation?.reason ?? '—'}</span>
                                </p>
                            )}
                        </div>
                    )}

                    {showReturnPanel && (
                    <div className="border-t border-gray-100 pt-4 space-y-3">
                        <h3 className="text-sm font-black text-gray-700">
                            🔄 自分の借用中
                            <span className="ml-2 text-xs text-gray-400">({safeMyLoans.length} 冊)</span>
                        </h3>

                        {returnRestricted && !canReturnHere && (
                            <div className="bg-amber-50 border border-amber-200 text-amber-900 p-3 rounded-xl text-xs font-bold leading-relaxed">
                                <p className="font-black mb-1">📡 社外ネットワークから接続中</p>
                                <p>{returnBlockedMessage}</p>
                            </div>
                        )}

                        {safeMyLoans.length === 0 ? (
                            <p className="text-xs text-gray-400 font-bold text-center py-3 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                現在借りている本はありません
                            </p>
                        ) : (
                            safeMyLoans.map((loan) => {
                                const loanInfo = getLoanDisplayInfo(loan);
                                let countdownText = '返却期限未設定';
                                let isOverdue = false;

                                if (loanInfo) {
                                    isOverdue = loanInfo.isOverdue;
                                    if (loanInfo.isOverdue) {
                                        countdownText = `期限超過: ${loanInfo.badgeText}`;
                                    } else {
                                        countdownText = `返却期限: ${loanInfo.finalDueDateText} ${loanInfo.durationLabel}`;
                                    }
                                } else if (loan.due_date) {
                                    const today = new Date();
                                    today.setHours(0, 0, 0, 0);
                                    const dueDate = new Date(loan.due_date);
                                    dueDate.setHours(0, 0, 0, 0);
                                    const diffDays = Math.ceil(
                                        (dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
                                    );

                                    if (diffDays > 0) {
                                        countdownText = `返却期限まで あと ${diffDays} 日`;
                                    } else if (diffDays === 0) {
                                        countdownText = '本日が返却期限です';
                                        isOverdue = true;
                                    } else {
                                        countdownText = `期限を ${Math.abs(diffDays)} 日超過`;
                                        isOverdue = true;
                                    }
                                }

                                return (
                                    <div
                                        key={loan?.id}
                                        className="bg-gray-50 p-3 rounded-xl border border-gray-200 flex gap-3 items-center justify-between"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <h4 className="font-black text-sm text-gray-900 truncate">
                                                {loan?.book?.title}
                                            </h4>
                                            {loan?.book?.copy_number > 1 && (
                                                <p className="text-[10px] text-gray-400 font-bold">
                                                    第{loan.book.copy_number}冊
                                                </p>
                                            )}
                                            <div className="flex flex-col gap-0.5 mt-1 font-bold">
                                                <p className="text-[11px] text-gray-400">
                                                    📅 貸出日:{' '}
                                                    {loan?.borrowed_at
                                                        ? new Date(loan.borrowed_at).toLocaleDateString()
                                                        : '不明'}
                                                </p>
                                                {loan.due_date && (
                                                    <p
                                                        className={`text-[11px] ${isOverdue ? 'text-red-500 font-black animate-pulse' : 'text-emerald-600'}`}
                                                    >
                                                        {countdownText}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <button
                                            onClick={() =>
                                                handleReturnSubmit(loan?.book?.id, loan?.book?.title)
                                            }
                                            disabled={
                                                processing || (returnRestricted && !canReturnHere)
                                            }
                                            className="bg-[#434343] border-b-4 border-[#2b2b2b] text-white font-black text-xs px-4 py-2.5 rounded-xl active:scale-[0.95] shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            返却
                                        </button>
                                    </div>
                                );
                            })
                        )}
                    </div>
                    )}
                </div>
                </div>

                <div className="space-y-4">
                        
                        {/* 🔍 極大キーワード検索窓 ＆ カテゴリバッジエリア */}
                        <div className="bg-white border border-gray-200 rounded-3xl p-4 md:p-5 shadow-sm space-y-4">
                            <div className="relative">
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="本のタイトル、ISBNで検索..."
                                    className="w-full bg-gray-50 border border-gray-300 rounded-2xl px-4 py-3 text-sm md:text-base text-gray-800 placeholder-gray-400 focus:border-gray-500 focus:ring-4 focus:ring-gray-100 focus:outline-none shadow-inner transition-all font-bold"
                                />
                                {searchQuery && (
                                    <button onClick={() => setSearchQuery('')} className="absolute right-3 top-3 text-[10px] md:text-xs font-black bg-gray-200 hover:bg-gray-300 text-gray-500 px-2.5 py-1 rounded-full transition-all">クリア</button>
                                )}
                            </div>

                            <div>
                                <label htmlFor="book-category-filter" className="sr-only">
                                    カテゴリ
                                </label>
                                <select
                                    id="book-category-filter"
                                    value={selectedCategory}
                                    onChange={(e) => setSelectedCategory(e.target.value)}
                                    className="w-full bg-gray-50 border border-gray-300 rounded-xl px-4 py-3 text-sm font-black text-gray-700 focus:border-gray-500 focus:outline-none focus:ring-4 focus:ring-gray-100"
                                >
                                    {categories.map((cat) => (
                                        <option key={cat} value={cat}>
                                            {cat === 'すべて' ? 'カテゴリ: すべて' : cat}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="flex justify-between items-center pl-1 mb-1">
                            <span className="text-xs font-black text-gray-400 uppercase tracking-wider">
                                🏢 社内配置ラック
                            </span>
                        </div>

                        {/* 🪵 絞り込み後のリアルウッド本棚 */}
                        {filteredBooks.length === 0 ? (
                            <div className="text-center py-16 bg-white rounded-3xl border-4 border-dashed border-gray-200 px-6">
                                <h3 className="font-black text-gray-600 text-sm">条件に合う本が見つかりませんでした</h3>
                                <p className="text-xs text-gray-400 font-bold mt-1">キーワードを変えてお試しください。</p>
                            </div>
                        ) : (
                            <div className="bg-[#3e2713] rounded-3xl p-4 md:p-6 shadow-2xl border-4 border-[#2b1a0c] space-y-8 relative overflow-hidden">
                                <div className="absolute inset-y-0 left-0 w-1.5 bg-black/20"></div>
                                <div className="absolute inset-y-0 right-0 w-1.5 bg-black/20"></div>
                                
                                {/* スマホは横2列、PCは画面幅に合わせて列を増やす */}
                                <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-x-4 md:gap-x-6 gap-y-12 relative z-10">
                                    {filteredBooks.map((book) => {
                                        const hasAvailable = book.availableCopies > 0;
                                        const stockLabel = formatStockLabel(
                                            book.availableCopies,
                                            book.totalCopies,
                                        );
                                        const bookKey = getBookKey(book);
                                        const isPulling = pullingBookKey === bookKey;
                                        const isSelected = showBookModal && selectedBook && getBookKey(selectedBook) === bookKey;

                                        return (
                                            <div key={bookKey} className="flex flex-col items-center group relative animate-fadeIn">
                                                <button
                                                    type="button"
                                                    onClick={() => handleBookClick(book)}
                                                    aria-label={`${book.title}の詳細を見る`}
                                                    className={`flex flex-col items-center w-full cursor-pointer transition-transform focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#3e2713] rounded-lg ${
                                                        isPulling || isSelected ? 'animate-bookPullOut z-20 relative' : 'hover:-translate-y-1 active:scale-[0.98]'
                                                    }`}
                                                >
                                                <div className={`relative w-24 h-32 md:w-28 md:h-36 ${!hasAvailable ? 'brightness-[0.35]' : ''}`}>
                                                    <BookCoverImage
                                                        isbn={book.isbn}
                                                        cover={book.cover}
                                                        title={book.title}
                                                        frameClassName="w-full h-full rounded-r-md shadow-xl border-l-4 border-black/30 overflow-hidden bg-gray-800"
                                                    />
                                                    {!hasAvailable && (
                                                        <div className="absolute inset-0 flex items-center justify-center bg-black/10 z-20"><span className="bg-red-600 text-white font-black text-[10px] md:text-[11px] px-2 py-0.5 rounded shadow transform -rotate-12 border border-red-400">全冊貸出中</span></div>
                                                    )}
                                                    {hasAvailable && stockLabel && (
                                                        <div className="absolute top-1 right-1 z-20">
                                                            <span className="bg-emerald-600/90 text-white font-black text-[8px] md:text-[9px] px-1.5 py-0.5 rounded shadow">
                                                                {stockLabel}
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                                {/* 🪵 リアルな立体ウッド棚のグラデーション表現 */}
                                                <div className="w-[112%] h-3.5 bg-gradient-to-b from-[#634121] via-[#52351a] to-[#26170a] shadow-md border-t border-[#734d28] rounded-b-sm mt-1 pointer-events-none"></div>
                                                <p className="mt-1.5 w-full min-h-[2.4em] px-0.5 text-center text-[10px] md:text-[11px] leading-tight font-bold text-amber-50 line-clamp-2">
                                                    {book.title}
                                                </p>
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                </div>

                <BookShelfDetailModal
                    show={showBookModal}
                    book={selectedBook}
                    onClose={handleCloseBookModal}
                    borrowRestricted={borrowRestricted}
                    canBorrowHere={canBorrowHere}
                    borrowLocation={borrowLocation}
                    alreadyBorrowed={isAlreadyBorrowedByUser(selectedBook?.isbn, myBorrowedIsbns)}
                />

            </div>
        </AuthenticatedLayout>
    );
}

