import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import InputError from '@/Components/InputError';

import BookCoverImage from '@/Components/BookCoverImage';
import BookCategoryPicker from '@/Components/BookCategoryPicker';
import CoverImagePicker from '@/Components/CoverImagePicker';
import { persistCoverUrl } from '@/utils/coverUpload';
import { normalizeIsbn } from '@/utils/bookCover';
import { DEFAULT_BOOK_CATEGORY, guessBookCategory } from '@/constants/bookCategories';

export default function BookCreate() {
    const [isbnInput, setIsbnInput] = useState('');
    const [isSearching, setIsSearching] = useState(false);
    const [lookupMessage, setLookupMessage] = useState('');
    const [existingCopies, setExistingCopies] = useState(0);
    const [showForm, setShowForm] = useState(false);
    const [coverMessage, setCoverMessage] = useState('');
    const [isFetchingCover, setIsFetchingCover] = useState(false);
    const abortRef = useRef(null);
    const categoryTouchedRef = useRef(false);
    const skipAutoCoverRef = useRef(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        isbn: '',
        title: '',
        category: DEFAULT_BOOK_CATEGORY,
        cover: '',
    });

    const resetForm = useCallback(() => {
        reset();
        setIsbnInput('');
        setLookupMessage('');
        setCoverMessage('');
        setExistingCopies(0);
        setShowForm(false);
        setIsFetchingCover(false);
        categoryTouchedRef.current = false;
        skipAutoCoverRef.current = false;
    }, [reset]);

    const suggestedCategory = (result, fallback = DEFAULT_BOOK_CATEGORY) => {
        if (categoryTouchedRef.current) {
            return fallback;
        }

        return result.category || guessBookCategory(result.title) || fallback;
    };

    const openManualForm = useCallback(
        (isbn = '', extras = {}) => {
            const cleanIsbn = normalizeIsbn(isbn);
            const extraCover = String(extras.cover ?? '').trim();
            setData((current) => ({
                ...current,
                isbn: cleanIsbn,
                title: extras.title || current.title || '',
                cover: extraCover || current.cover || '',
                category: suggestedCategory(extras, current.category),
            }));
            setShowForm(true);
        },
        [setData],
    );

    const applyLookupResult = useCallback(
        (result) => {
            const coverUrl = String(result.cover ?? '').trim();
            const copies = result.existingCopies ?? 0;
            setExistingCopies(copies);
            skipAutoCoverRef.current = false;
            setData((current) => ({
                ...current,
                isbn: result.isbn,
                title: result.title || current.title || '',
                cover: coverUrl || current.cover || '',
                category: suggestedCategory(result, current.category),
            }));
            if (copies > 0) {
                setLookupMessage(
                    `このISBNは既に ${copies} 冊登録済みです。追加の1冊として登録できます。`,
                );
            } else {
                setLookupMessage('');
            }
            setShowForm(true);
        },
        [setData],
    );

    const lookupIsbn = useCallback(
        async (rawIsbn, titleHint = '') => {
            const cleanIsbn = normalizeIsbn(rawIsbn);

            if (cleanIsbn.length !== 13) {
                setLookupMessage('');
                if (!showForm) {
                    setShowForm(false);
                }
                return;
            }

            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            setIsSearching(true);
            setLookupMessage('');

            try {
                const params = new URLSearchParams({ isbn: cleanIsbn });
                const titleQuery = String(titleHint ?? '').trim();

                if (titleQuery) {
                    params.set('title', titleQuery);
                }

                const response = await fetch(`${route('admin.books.lookup')}?${params}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                const payload = await response.json();

                if (response.status === 422) {
                    setShowForm(false);
                    setExistingCopies(0);
                    setLookupMessage(payload.message);
                    return;
                }

                if (response.status === 404) {
                    setLookupMessage(payload.message);
                    openManualForm(cleanIsbn, payload);
                    return;
                }

                if (!response.ok) {
                    setLookupMessage('書籍情報の取得に失敗しました。手動入力で登録できます。');
                    openManualForm(cleanIsbn, payload);
                    return;
                }

                applyLookupResult(payload);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                setLookupMessage('通信エラーが発生しました。手動入力で登録できます。');
                openManualForm(cleanIsbn);
            } finally {
                if (!controller.signal.aborted) {
                    setIsSearching(false);
                }
            }
        },
        [applyLookupResult, openManualForm, showForm],
    );

    useEffect(() => {
        const cleanIsbn = normalizeIsbn(isbnInput);
        if (cleanIsbn.length !== 13) {
            return undefined;
        }

        const timer = setTimeout(() => lookupIsbn(cleanIsbn), 400);
        return () => clearTimeout(timer);
    }, [isbnInput, lookupIsbn]);

    useEffect(() => {
        return () => abortRef.current?.abort();
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.books.store'), {
            preserveScroll: true,
            transform: (formData) => ({
                ...formData,
                isbn: normalizeIsbn(formData.isbn),
                cover: String(formData.cover ?? '').trim(),
            }),
            onSuccess: () => {
                resetForm();
            },
        });
    };

    const handleFetchTitle = async () => {
        const isbn = normalizeIsbn(data.isbn);

        if (isbn.length !== 13) {
            setLookupMessage('ISBNが13桁のときタイトルを再取得できます。');
            return;
        }

        await lookupIsbn(isbn, data.title);
    };

    const handleFetchCover = async () => {
        const isbn = normalizeIsbn(data.isbn);

        if (isbn.length !== 13) {
            setCoverMessage('ISBNが13桁のとき表紙を再取得できます。');
            return;
        }

        setIsFetchingCover(true);
        setCoverMessage('');
        skipAutoCoverRef.current = false;

        try {
            await lookupIsbn(isbn, data.title);
            setCoverMessage('表紙を検索しました。URLが出たら登録してください。');
        } finally {
            setIsFetchingCover(false);
        }
    };

    const handleCoverUrlBlur = async () => {
        const pasted = String(data.cover ?? '').trim();

        if (!pasted || pasted.startsWith('/covers/')) {
            return;
        }

        const stored = await persistCoverUrl(pasted);

        skipAutoCoverRef.current = true;
        setData('cover', stored);

        if (stored.startsWith('/covers/')) {
            setCoverMessage('表紙URLを保存しました。登録すると本棚にすぐ表示されます。');
        }
    };

    const handleDisplayCover = (url) => {
        const coverUrl = String(url ?? '').trim();

        if (!coverUrl || skipAutoCoverRef.current) {
            return;
        }

        setData((current) => {
            if (String(current.cover ?? '').trim()) {
                return current;
            }

            return { ...current, cover: coverUrl };
        });
    };

    const handleTitleChange = (title) => {
        setData((current) => ({
            ...current,
            title,
            category: categoryTouchedRef.current
                ? current.category
                : guessBookCategory(title),
        }));
    };

    const handleCategoryChange = (category) => {
        categoryTouchedRef.current = true;
        setData('category', category);
    };

    const canSubmit =
        !processing &&
        normalizeIsbn(data.isbn).length === 13 &&
        data.title.trim().length > 0;

    return (
        <AdminLayout title="新着書籍の台帳追加登録" maxWidth="max-w-xl">
            <div className="space-y-6">
                <div className="flex items-center justify-between gap-3 border-b border-gray-200 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl" aria-hidden="true">
                            🆕
                        </span>
                        <h2 className="font-black text-2xl text-gray-800 tracking-wide">
                            新着書籍の台帳追加登録
                        </h2>
                    </div>
                    <Link
                        href={route('admin.books.edit')}
                        className="text-xs font-black text-[#00897b] hover:underline shrink-0"
                    >
                        全資料編集 →
                    </Link>
                </div>

                <Link
                    href={route('admin.books.import')}
                    className="flex items-center justify-between gap-3 bg-[#f0f9ff] border-2 border-[#00a0e9]/30 rounded-2xl px-4 py-3 transition-all hover:bg-[#e0f2fe] hover:border-[#00a0e9]/50"
                >
                    <div className="flex items-center gap-3 min-w-0">
                        <span className="text-2xl shrink-0" aria-hidden="true">
                            📥
                        </span>
                        <div className="min-w-0">
                            <p className="font-black text-sm text-gray-800">Excel入出力</p>
                            <p className="text-xs text-gray-500 font-bold truncate">
                                台帳の保存と、Excel / CSV からの一括登録
                            </p>
                        </div>
                    </div>
                    <span className="text-xs font-black text-[#00a0e9] shrink-0">開く →</span>
                </Link>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm space-y-3">
                        <label
                            htmlFor="isbn-input"
                            className="block text-xs font-black text-gray-400 uppercase tracking-wider"
                        >
                            書籍のISBNコード（13桁）
                        </label>
                        <input
                            id="isbn-input"
                            type="text"
                            inputMode="numeric"
                            value={isbnInput}
                            onChange={(e) => setIsbnInput(e.target.value)}
                            placeholder="例: 9784815608774"
                            maxLength={17}
                            className="w-full bg-gray-50 border border-gray-300 rounded-xl px-4 py-3 text-base text-gray-800 focus:border-gray-500 focus:outline-none font-bold"
                        />
                        <div className="flex items-center justify-between gap-3">
                            {isSearching ? (
                                <p className="text-xs text-[#00a0e9] font-black animate-pulse">
                                    書籍データを取得中...
                                </p>
                            ) : (
                                <p className="text-xs text-gray-400 font-bold">
                                    13桁入力で自動取得します
                                </p>
                            )}
                            <button
                                type="button"
                                onClick={() => openManualForm(isbnInput)}
                                className="text-xs font-black text-[#00a0e9] hover:underline shrink-0"
                            >
                                手動入力
                            </button>
                        </div>
                        {lookupMessage && (
                            <p className="text-xs text-amber-600 font-bold">{lookupMessage}</p>
                        )}
                        <InputError message={errors.isbn} className="text-xs font-bold" />
                    </div>

                    {showForm && (
                        <div className="bg-white border-2 border-gray-200 rounded-3xl p-5 shadow-md space-y-5 animate-fadeIn">
                            <div className="border-b border-gray-100 pb-3 space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <label
                                        htmlFor="book-title"
                                        className="block text-xs font-black text-gray-400"
                                    >
                                        書籍タイトル
                                    </label>
                                    <button
                                        type="button"
                                        onClick={handleFetchTitle}
                                        disabled={processing || isSearching || isFetchingCover}
                                        className="text-xs font-black text-[#00a0e9] hover:underline shrink-0 disabled:opacity-50"
                                    >
                                        {isSearching ? '取得中…' : 'タイトルを再取得'}
                                    </button>
                                </div>
                                <input
                                    id="book-title"
                                    type="text"
                                    value={data.title}
                                    onChange={(e) => handleTitleChange(e.target.value)}
                                    placeholder="タイトルを入力してください"
                                    className="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm font-black text-gray-900 focus:border-gray-500 focus:outline-none"
                                />
                                {!String(data.title ?? '').trim() && (
                                    <p className="text-[11px] font-bold text-gray-500">
                                        見つからないときは再取得するか、手入力できます
                                    </p>
                                )}
                                <InputError message={errors.title} className="text-xs font-bold" />
                            </div>

                            <div className="flex flex-col items-center justify-center bg-gray-50 border border-gray-100 rounded-2xl p-4 space-y-3">
                                <BookCoverImage
                                    isbn={data.isbn}
                                    cover={data.cover}
                                    title={data.title}
                                    onDisplayUrl={handleDisplayCover}
                                />
                                <p className="text-[11px] font-bold text-gray-500 text-center">
                                    検索で表紙が出ないときは、画像ファイルを選ぶか、URLを入力できます
                                </p>
                                <input
                                    type="text"
                                    inputMode="url"
                                    value={data.cover}
                                    onChange={(e) => {
                                        skipAutoCoverRef.current = true;
                                        setData('cover', e.target.value);
                                    }}
                                    onBlur={handleCoverUrlBlur}
                                    placeholder="https://example.com/cover.jpg"
                                    className="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-mono text-gray-800 focus:border-gray-500 focus:outline-none"
                                />
                                <div className="flex flex-wrap items-center justify-center gap-2">
                                    <CoverImagePicker
                                        disabled={processing || isFetchingCover}
                                        onUploaded={(url) => {
                                            skipAutoCoverRef.current = true;
                                            setData('cover', url);
                                            setCoverMessage('表紙画像を設定しました。登録すると反映されます。');
                                        }}
                                        onError={(message) => setCoverMessage(message)}
                                    />
                                    <button
                                        type="button"
                                        onClick={handleFetchCover}
                                        disabled={processing || isFetchingCover}
                                        className="px-3 py-1.5 text-[11px] font-black rounded-lg border border-[#00a0e9] text-[#007bbf] bg-sky-50 hover:bg-sky-100 disabled:opacity-50"
                                    >
                                        {isFetchingCover ? '取得中…' : '表紙を再取得'}
                                    </button>
                                    {data.cover ? (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                skipAutoCoverRef.current = true;
                                                setData('cover', '');
                                                setCoverMessage('表紙をクリアしました。');
                                            }}
                                            className="px-3 py-1.5 text-[11px] font-black rounded-lg border border-gray-300 text-gray-600 bg-white hover:bg-gray-50"
                                        >
                                            表紙をクリア
                                        </button>
                                    ) : null}
                                </div>
                                {coverMessage && (
                                    <p className="text-[11px] font-bold text-gray-500 text-center">
                                        {coverMessage}
                                    </p>
                                )}
                                <InputError message={errors.cover} className="text-xs font-bold" />
                            </div>

                            <div className="space-y-2 pt-2 border-t border-gray-100">
                                <label
                                    htmlFor="book-category"
                                    className="block text-xs font-black text-gray-400 uppercase tracking-wider"
                                >
                                    カテゴリ
                                </label>
                                <BookCategoryPicker
                                    id="book-category"
                                    value={data.category}
                                    onChange={handleCategoryChange}
                                    disabled={processing}
                                />
                                <InputError message={errors.category} className="text-xs font-bold" />
                            </div>

                            <div className="pt-3">
                                <button
                                    type="submit"
                                    disabled={!canSubmit}
                                    className="w-full bg-[#00a0e9] border-b-4 border-[#007bbf] text-white font-black text-base py-4 rounded-2xl shadow-md transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing
                                        ? '登録中...'
                                        : existingCopies > 0
                                          ? `在庫を1冊追加する（第${existingCopies + 1}冊）`
                                          : 'この内容で本棚に正式登録する'}
                                </button>
                            </div>
                        </div>
                    )}
                </form>
            </div>
        </AdminLayout>
    );
}
