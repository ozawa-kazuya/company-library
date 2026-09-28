import { useEffect, useMemo, useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import BookCoverImage from '@/Components/BookCoverImage';
import CoverImagePicker from '@/Components/CoverImagePicker';
import InputError from '@/Components/InputError';
import { formatCopyLabel } from '@/utils/bookGrouping';
import { persistCoverUrl } from '@/utils/coverUpload';
import { normalizeIsbn } from '@/utils/bookCover';
import { BOOK_CATEGORIES, DEFAULT_BOOK_CATEGORY } from '@/constants/bookCategories';

export default function BookEdit({ books: initialBooks = [] }) {
    const [query, setQuery] = useState('');
    const [fetchingCoverId, setFetchingCoverId] = useState(null);
    const [lookingUpTitleId, setLookingUpTitleId] = useState(null);
    const [coverMessages, setCoverMessages] = useState({});
    const booksRef = useRef([]);
    const isbnLookupTimerRef = useRef(null);
    const isbnLookupAbortRef = useRef(null);

    const { data, setData, put, processing, errors } = useForm({
        books: initialBooks.map((book) => ({
            id: book.id,
            title: book.title ?? '',
            isbn: book.isbn ?? '',
            cover: book.cover ?? '',
            category: book.category ?? DEFAULT_BOOK_CATEGORY,
            copy_number: book.copy_number,
            total_copies_in_group: book.total_copies_in_group ?? 1,
            is_borrowed: book.is_borrowed,
            is_off_shelf: book.is_off_shelf,
            borrower_name: book.borrower_name ?? '',
        })),
    });

    booksRef.current = data.books;

    useEffect(() => {
        return () => {
            window.clearTimeout(isbnLookupTimerRef.current);
            isbnLookupAbortRef.current?.abort();
        };
    }, []);

    const setCoverMessage = (bookId, message) => {
        setCoverMessages((current) => ({
            ...current,
            [bookId]: message,
        }));
    };

    const applyCoverToIsbnGroup = (sourceIndex, cover) => {
        const isbn = normalizeIsbn(booksRef.current[sourceIndex]?.isbn);
        const canSync = /^\d{13}$/.test(isbn);

        setData(
            'books',
            booksRef.current.map((book, index) => {
                if (index === sourceIndex || (canSync && normalizeIsbn(book.isbn) === isbn)) {
                    return { ...book, cover };
                }

                return book;
            }),
        );
    };

    const filteredIndexes = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        if (!normalized) {
            return data.books.map((_, index) => index);
        }

        return data.books.reduce((indexes, book, index) => {
            const haystack = [book.title, book.isbn, book.category].join(' ').toLowerCase();
            if (haystack.includes(normalized)) {
                indexes.push(index);
            }
            return indexes;
        }, []);
    }, [data.books, query]);

    const applyLookupToBook = (index, isbn, payload) => {
        const title = String(payload.title ?? '').trim();
        const cover = String(payload.cover ?? '').trim();
        const canSyncCover = cover !== '' && /^\d{13}$/.test(isbn);

        setData(
            'books',
            booksRef.current.map((book, bookIndex) => {
                if (bookIndex === index) {
                    return {
                        ...book,
                        title: title || book.title,
                        cover: cover || book.cover,
                    };
                }

                if (canSyncCover && normalizeIsbn(book.isbn) === isbn) {
                    return { ...book, cover };
                }

                return book;
            }),
        );
    };

    const lookupTitleByIsbn = async (index, isbn) => {
        const book = booksRef.current[index];

        if (!book || normalizeIsbn(book.isbn) !== isbn) {
            return;
        }

        isbnLookupAbortRef.current?.abort();
        const controller = new AbortController();
        isbnLookupAbortRef.current = controller;

        setLookingUpTitleId(book.id);
        setCoverMessage(book.id, 'ISBNからタイトルを取得しています…');

        try {
            const response = await fetch(
                `${route('admin.books.lookup')}?isbn=${encodeURIComponent(isbn)}`,
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            );
            const payload = await response.json();

            if (normalizeIsbn(booksRef.current[index]?.isbn ?? '') !== isbn) {
                return;
            }

            if (response.ok && String(payload.title ?? '').trim()) {
                applyLookupToBook(index, isbn, payload);
                setCoverMessage(
                    book.id,
                    payload.cover
                        ? 'タイトルと表紙を更新しました。保存すると反映されます。'
                        : 'タイトルを更新しました。保存すると反映されます。',
                );
                return;
            }

            if (response.status === 404) {
                setCoverMessage(
                    book.id,
                    'このISBNの書誌が見つかりませんでした。タイトルは手入力できます。',
                );
                return;
            }

            setCoverMessage(book.id, '書誌の取得に失敗しました。タイトルは手入力できます。');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            setCoverMessage(book.id, '書誌の取得に失敗しました。タイトルは手入力できます。');
        } finally {
            if (!controller.signal.aborted) {
                setLookingUpTitleId((current) => (current === book.id ? null : current));
            }
        }
    };

    const scheduleIsbnLookup = (index, isbn) => {
        window.clearTimeout(isbnLookupTimerRef.current);
        isbnLookupAbortRef.current?.abort();
        setLookingUpTitleId(null);

        const cleanIsbn = normalizeIsbn(isbn);

        if (cleanIsbn.length !== 13) {
            return;
        }

        isbnLookupTimerRef.current = window.setTimeout(() => {
            lookupTitleByIsbn(index, cleanIsbn);
        }, 400);
    };

    const handleFieldChange = (index, field, value) => {
        if (field === 'cover') {
            const nextBooks = [...data.books];
            nextBooks[index] = {
                ...nextBooks[index],
                cover: value,
            };
            setData('books', nextBooks);
            setCoverMessage(data.books[index].id, '');
            return;
        }

        const nextValue = field === 'isbn' ? value.replace(/\D/g, '').slice(0, 13) : value;
        const nextBooks = [...data.books];
        nextBooks[index] = {
            ...nextBooks[index],
            [field]: nextValue,
        };
        setData('books', nextBooks);

        if (field === 'isbn') {
            scheduleIsbnLookup(index, nextValue);
        }
    };

    const handleFetchCover = async (index) => {
        const book = data.books[index];
        const isbn = normalizeIsbn(book.isbn);

        if (isbn.length !== 13) {
            setCoverMessage(book.id, 'ISBNが13桁のとき表紙を再取得できます。');
            return;
        }

        setFetchingCoverId(book.id);
        setCoverMessage(book.id, '');

        try {
            const response = await fetch(
                `${route('admin.books.lookup')}?isbn=${encodeURIComponent(isbn)}&title=${encodeURIComponent(book.title)}`,
                { headers: { Accept: 'application/json' } },
            );
            const payload = await response.json();
            const coverUrl = String(payload.cover ?? '').trim();

            if (coverUrl) {
                applyCoverToIsbnGroup(index, coverUrl);
                setCoverMessage(book.id, '表紙を更新しました。保存すると反映されます。');
            } else {
                setCoverMessage(
                    book.id,
                    '表紙が見つかりませんでした。表紙画像を選ぶか、URLを入力できます。',
                );
            }
        } catch {
            setCoverMessage(book.id, '表紙の取得に失敗しました。表紙画像を選ぶか、URLを入力できます。');
        } finally {
            setFetchingCoverId(null);
        }
    };

    const handleCoverUrlBlur = async (index) => {
        const book = booksRef.current[index];
        const pasted = String(book?.cover ?? '').trim();

        if (!pasted) {
            applyCoverToIsbnGroup(index, '');
            return;
        }

        const stored = await persistCoverUrl(pasted);
        applyCoverToIsbnGroup(index, stored);

        if (stored.startsWith('/covers/')) {
            setCoverMessage(book.id, '表紙URLを保存しました。一括保存すると本棚にすぐ表示されます。');
        }
    };

    const handleClearCover = (index) => {
        applyCoverToIsbnGroup(index, '');
        setCoverMessage(data.books[index].id, '表紙をクリアしました。保存すると反映されます。');
    };

    const missingCoverCount = data.books.filter(
        (book) => !String(book.cover ?? '').trim(),
    ).length;

    const handleBackfillMissingCovers = () => {
        if (missingCoverCount === 0) {
            return;
        }

        if (
            !confirm(
                `表紙がない ${missingCoverCount} 冊を検索します。冊数が多いと数分かかることがあります。`,
            )
        ) {
            return;
        }

        router.post(route('admin.books.covers.backfill'), {}, { preserveScroll: true });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('admin.books.bulk-update'), {
            preserveScroll: true,
            transform: (formData) => ({
                books: formData.books.map(({ id, title, isbn, category, cover }) => ({
                    id,
                    title,
                    isbn,
                    category,
                    cover: String(cover ?? '').trim(),
                })),
            }),
        });
    };

    const handleDelete = (book) => {
        if (book.is_borrowed) {
            return;
        }

        if (!confirm(`「${book.title}」を廃棄して台帳から外しますか？\n情報が古い本など、棚に置かない資料向けです。`)) {
            return;
        }

        router.delete(route('admin.books.destroy', book.id), {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="全資料編集" maxWidth="max-w-7xl">
            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-200 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl" aria-hidden="true">
                            ✏️
                        </span>
                        <div>
                            <h2 className="font-black text-2xl text-gray-800 tracking-wide">
                                全資料編集
                            </h2>
                            <p className="text-xs text-gray-500 font-bold mt-1">
                                登録済み {data.books.length} 冊を一括で編集できます
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        <a
                            href={route('admin.books.export')}
                            className="px-3 py-2 text-xs font-black rounded-lg border border-[#00897b] text-[#00695c] bg-emerald-50 hover:bg-emerald-100"
                        >
                            Excelで保存
                        </a>
                        <button
                            type="button"
                            onClick={handleBackfillMissingCovers}
                            disabled={processing || missingCoverCount === 0}
                            className="px-3 py-2 text-xs font-black rounded-lg border border-[#00a0e9] text-[#007bbf] bg-sky-50 hover:bg-sky-100 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            表紙がない本だけ再取得
                            {missingCoverCount > 0 ? `（${missingCoverCount}）` : ''}
                        </button>
                    </div>
                </div>

                {errors.books && typeof errors.books === 'string' && (
                    <div className="bg-red-500 text-white p-4 rounded-xl font-black text-xs md:text-sm shadow">
                        {errors.books}
                    </div>
                )}

                <div className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
                    <input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="タイトル、ISBN、カテゴリで絞り込み..."
                        className="w-full bg-gray-50 border border-gray-300 rounded-xl px-4 py-3 text-sm font-bold text-gray-800 focus:border-gray-500 focus:outline-none"
                    />
                </div>

                <form onSubmit={handleSubmit} className="space-y-3">
                    {filteredIndexes.length === 0 ? (
                        <div className="bg-white border border-gray-200 rounded-2xl p-8 text-center text-sm font-bold text-gray-500">
                            該当する資料がありません
                        </div>
                    ) : (
                        filteredIndexes.map((index) => {
                            const book = data.books[index];
                            const copyLabel = formatCopyLabel(
                                book.copy_number,
                                book.total_copies_in_group ?? 1,
                            );
                            const isFetchingCover = fetchingCoverId === book.id;

                            return (
                                <div
                                    key={book.id}
                                    className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm space-y-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-[10px] font-black text-gray-400 uppercase">
                                            ID {book.id}
                                            {copyLabel && (
                                                <span className="ml-2 text-[#00a0e9]">
                                                    {copyLabel}
                                                </span>
                                            )}
                                        </span>
                                        {book.is_borrowed ? (
                                            <span className="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-black rounded-md">
                                                貸出中: {book.borrower_name || '不明'}
                                            </span>
                                        ) : book.is_off_shelf ? (
                                            <span className="px-2 py-0.5 bg-gray-100 text-gray-600 border border-gray-200 text-[10px] font-black rounded-md">
                                                在庫外（Excel貸出）
                                            </span>
                                        ) : (
                                            <span className="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-black rounded-md">
                                                保管中
                                            </span>
                                        )}
                                    </div>

                                    <div className="flex flex-col sm:flex-row gap-4">
                                        <div className="shrink-0">
                                            <BookCoverImage
                                                isbn={book.isbn}
                                                cover={book.cover}
                                                title={book.title}
                                                frameClassName="w-[72px] h-[102px] rounded-md shadow border border-gray-200 overflow-hidden bg-gray-200"
                                            />
                                        </div>

                                        <div className="flex-1 min-w-0 space-y-3">
                                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-3">
                                                <div className="lg:col-span-5 space-y-1">
                                                    <label className="text-[10px] font-black text-gray-400">
                                                        タイトル
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={book.title}
                                                        onChange={(e) =>
                                                            handleFieldChange(
                                                                index,
                                                                'title',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold text-gray-800 focus:border-gray-500 focus:outline-none"
                                                        required
                                                        aria-busy={lookingUpTitleId === book.id}
                                                    />
                                                    <InputError
                                                        message={errors[`books.${index}.title`]}
                                                        className="text-[10px] font-bold"
                                                    />
                                                </div>

                                                <div className="lg:col-span-4 space-y-1">
                                                    <label className="text-[10px] font-black text-gray-400">
                                                        ISBN
                                                    </label>
                                                    <input
                                                        type="text"
                                                        inputMode="numeric"
                                                        value={book.isbn}
                                                        onChange={(e) =>
                                                            handleFieldChange(
                                                                index,
                                                                'isbn',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono font-bold text-gray-800 focus:border-gray-500 focus:outline-none"
                                                        required
                                                    />
                                                    <InputError
                                                        message={errors[`books.${index}.isbn`]}
                                                        className="text-[10px] font-bold"
                                                    />
                                                    {lookingUpTitleId === book.id && (
                                                        <p className="text-[11px] font-bold text-[#007bbf]">
                                                            ISBNからタイトルを取得しています…
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="lg:col-span-3 space-y-1">
                                                    <label className="text-[10px] font-black text-gray-400">
                                                        カテゴリ
                                                    </label>
                                                    <select
                                                        value={book.category}
                                                        onChange={(e) =>
                                                            handleFieldChange(
                                                                index,
                                                                'category',
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold text-gray-800 focus:border-gray-500 focus:outline-none"
                                                    >
                                                        {BOOK_CATEGORIES.map((category) => (
                                                            <option key={category} value={category}>
                                                                {category}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                            </div>

                                            <div className="space-y-1">
                                                <label className="text-[10px] font-black text-gray-400">
                                                    表紙
                                                </label>
                                                <input
                                                    type="text"
                                                    inputMode="url"
                                                    value={book.cover}
                                                    onChange={(e) =>
                                                        handleFieldChange(
                                                            index,
                                                            'cover',
                                                            e.target.value,
                                                        )
                                                    }
                                                    onBlur={() => handleCoverUrlBlur(index)}
                                                    placeholder="https://example.com/cover.jpg"
                                                    className="w-full bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono text-gray-800 focus:border-gray-500 focus:outline-none"
                                                />
                                                <InputError
                                                    message={errors[`books.${index}.cover`]}
                                                    className="text-[10px] font-bold"
                                                />
                                                <div className="flex flex-wrap gap-2 pt-1">
                                                    <CoverImagePicker
                                                        disabled={processing || isFetchingCover}
                                                        onUploaded={(url) => {
                                                            applyCoverToIsbnGroup(index, url);
                                                            setCoverMessage(
                                                                book.id,
                                                                '表紙画像を設定しました。保存すると反映されます。',
                                                            );
                                                        }}
                                                        onError={(message) =>
                                                            setCoverMessage(book.id, message)
                                                        }
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => handleFetchCover(index)}
                                                        disabled={isFetchingCover || lookingUpTitleId === book.id}
                                                        className="px-3 py-1.5 text-[11px] font-black rounded-lg border border-[#00a0e9] text-[#007bbf] bg-sky-50 hover:bg-sky-100 disabled:opacity-50"
                                                    >
                                                        {isFetchingCover
                                                            ? '取得中…'
                                                            : '表紙を再取得'}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleClearCover(index)}
                                                        className="px-3 py-1.5 text-[11px] font-black rounded-lg border border-gray-300 text-gray-600 bg-white hover:bg-gray-50"
                                                    >
                                                        表紙をクリア
                                                    </button>
                                                </div>
                                                {coverMessages[book.id] && (
                                                    <p className="text-[11px] font-bold text-gray-500">
                                                        {coverMessages[book.id]}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex items-center justify-between gap-3">
                                                <p className="text-xs text-gray-500 font-bold">
                                                    {book.is_borrowed
                                                        ? '返却後に廃棄可能'
                                                        : book.is_off_shelf
                                                          ? '在庫外（廃棄可）'
                                                          : '編集・廃棄可'}
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(book)}
                                                    disabled={book.is_borrowed}
                                                    className="px-3 py-2 text-xs font-black rounded-lg border border-red-200 text-red-600 bg-red-50 hover:bg-red-100 disabled:opacity-40 disabled:cursor-not-allowed"
                                                >
                                                    廃棄
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}

                    <div className="pt-2 sticky bottom-4">
                        <button
                            type="submit"
                            disabled={processing || data.books.length === 0}
                            className="w-full bg-[#00897b] border-b-4 border-[#00695c] text-white font-black text-base py-4 rounded-2xl shadow-md transition-all active:scale-[0.98] disabled:opacity-50"
                        >
                            {processing ? '保存中…' : '変更内容を一括保存する'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
