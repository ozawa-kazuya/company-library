import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import BookCoverImage from '@/Components/BookCoverImage';
import { getBookStockSummary } from '@/utils/bookShelfDetail';

export default function BookShelfDetailModal({
    show = false,
    book = null,
    onClose,
    borrowRestricted = false,
    canBorrowHere = true,
    borrowLocation = {},
    alreadyBorrowed = false,
}) {
    if (!book) {
        return null;
    }

    const stock = getBookStockSummary(book);
    const borrowBlocked = borrowRestricted && !canBorrowHere;
    const borrowUrl = `${route('books.borrowScan')}?isbn=${encodeURIComponent(book.isbn ?? '')}`;

    return (
        <Transition show={show} leave="duration-200">
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" aria-hidden="true" />
                </TransitionChild>

                <div className="fixed inset-0 flex items-end justify-center p-3 sm:items-center sm:p-4">
                    <TransitionChild
                        enter="ease-out duration-350"
                        enterFrom="opacity-0 translate-y-16 scale-95"
                        enterTo="opacity-100 translate-y-0 scale-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100 translate-y-0 scale-100"
                        leaveTo="opacity-0 translate-y-12 scale-95"
                    >
                        <DialogPanel className="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl animate-bookCardRise">
                            <div
                                className={`px-5 pt-5 pb-4 border-b ${
                                    alreadyBorrowed
                                        ? 'bg-gradient-to-b from-amber-50 to-white border-amber-100'
                                        : stock.hasAvailable
                                          ? 'bg-gradient-to-b from-emerald-50 to-white border-emerald-100'
                                          : 'bg-gradient-to-b from-red-50 to-white border-red-100'
                                }`}
                            >
                                <div className="flex justify-center mb-4">
                                    <div
                                        className={`relative ${
                                            stock.hasAvailable && !alreadyBorrowed ? 'animate-glowPulse rounded-xl' : ''
                                        }`}
                                    >
                                        <BookCoverImage
                                            isbn={book.isbn}
                                            cover={book.cover}
                                            title={book.title}
                                            frameClassName="w-28 h-40 md:w-32 md:h-44 rounded-lg shadow-2xl border-l-4 border-black/20 overflow-hidden bg-gray-800"
                                        />
                                        {!stock.hasAvailable && (
                                            <span className="absolute inset-0 flex items-center justify-center">
                                                <span className="bg-red-600 text-white font-black text-xs px-3 py-1 rounded shadow-lg border border-red-400 animate-stampBounce">
                                                    今は空きません
                                                </span>
                                            </span>
                                        )}
                                        {stock.hasAvailable && alreadyBorrowed && (
                                            <span className="absolute inset-0 flex items-center justify-center">
                                                <span className="bg-amber-500 text-white font-black text-xs px-3 py-1 rounded shadow-lg border border-amber-300">
                                                    借用中
                                                </span>
                                            </span>
                                        )}
                                    </div>
                                </div>

                                <h3 className="font-black text-lg text-gray-900 text-center leading-snug">
                                    {book.title}
                                </h3>
                            </div>

                            <div className="px-5 py-4 space-y-4">
                                <div className="flex flex-wrap gap-2 justify-center">
                                    {book.category && (
                                        <span className="text-[10px] font-black bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full">
                                            {book.category}
                                        </span>
                                    )}
                                    <span
                                        className={`text-[10px] font-black px-2.5 py-1 rounded-full ${
                                            alreadyBorrowed
                                                ? 'bg-amber-100 text-amber-700'
                                                : stock.hasAvailable
                                                  ? 'bg-emerald-100 text-emerald-700'
                                                  : 'bg-red-100 text-red-700'
                                        }`}
                                    >
                                        {alreadyBorrowed
                                            ? '📌 あなたは借用中'
                                            : stock.hasAvailable
                                              ? '✨ 借りられます'
                                              : '📛 全冊貸出中'}
                                    </span>
                                    <span className="text-[10px] font-black bg-sky-50 text-sky-700 px-2.5 py-1 rounded-full">
                                        {stock.displayStock}
                                    </span>
                                </div>

                                {book.isbn && (
                                    <p className="text-[10px] text-gray-400 font-bold text-center font-mono">
                                        ISBN {book.isbn}
                                    </p>
                                )}

                                {alreadyBorrowed && (
                                    <div className="bg-amber-50 border border-amber-200 text-amber-900 p-3 rounded-xl text-xs font-bold leading-relaxed">
                                        同じ本は同時に1冊までです。返却してから再度お借りください。
                                    </div>
                                )}

                                {borrowBlocked && (
                                    <div className="bg-amber-50 border border-amber-200 text-amber-900 p-3 rounded-xl text-xs font-bold leading-relaxed">
                                        {borrowLocation?.message ??
                                            '貸出は社内ネットワークからのみ可能です。'}
                                    </div>
                                )}

                                <div className="flex gap-2 pt-1">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="flex-1 py-3.5 rounded-xl border-2 border-gray-200 text-gray-600 font-black text-sm active:scale-[0.98] transition-all"
                                    >
                                        本棚に戻す
                                    </button>
                                    {stock.hasAvailable && !alreadyBorrowed && (
                                        borrowBlocked ? (
                                            <button
                                                type="button"
                                                disabled
                                                className="flex-1 py-3.5 rounded-xl bg-gray-300 text-white font-black text-sm cursor-not-allowed opacity-60"
                                            >
                                                この本を借りる
                                            </button>
                                        ) : (
                                            <Link
                                                href={borrowUrl}
                                                className="flex-1 py-3.5 rounded-xl text-center text-white bg-[#00a0e9] border-b-4 border-[#007bbf] font-black text-sm active:scale-[0.98] transition-all shadow-sm"
                                            >
                                                この本を借りる
                                            </Link>
                                        )
                                    )}
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
