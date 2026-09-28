import { useCallback, useEffect, useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import FlashBanner from '@/Components/FlashBanner';
import IsbnBarcodeScanner from '@/Components/IsbnBarcodeScanner';

function normalizeIsbn(value) {
    return value.replace(/[-\s_＿]/g, '').trim();
}

export default function BorrowScan({ libraryLocation = {}, myBorrowedIsbns = [] }) {
    const [inputMode, setInputMode] = useState('manual');
    const [scanNotice, setScanNotice] = useState('');

    const locationRestricted = libraryLocation?.restricted ?? false;
    const canBorrowHere = libraryLocation?.allowedHere ?? true;
    const locationBlockedMessage =
        libraryLocation?.message ??
        '貸出は社内ネットワークからのみ可能です。オフィスで借り出してください。';

    const { data, setData, post, processing, errors, transform } = useForm({
        isbn: '',
        duration: '14',
    });

    transform((formData) => ({
        ...formData,
        isbn: normalizeIsbn(formData.isbn),
        duration: Number(formData.duration),
    }));

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const isbnFromShelf = normalizeIsbn(params.get('isbn') ?? '');
        if (isbnFromShelf.length >= 10) {
            setData('isbn', isbnFromShelf);
            setScanNotice(`本棚から選択しました（ISBN ${isbnFromShelf}）`);
        }
    }, [setData]);

    const handleIsbnDetected = useCallback(
        (isbn) => {
            setData('isbn', isbn);
            setScanNotice(`ISBN ${isbn} を読み取りました`);
        },
        [setData],
    );

    const switchMode = (mode) => {
        setInputMode(mode);
        setScanNotice('');
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        if (locationRestricted && !canBorrowHere) {
            alert(locationBlockedMessage);
            return;
        }

        const cleanIsbn = normalizeIsbn(data.isbn);

        if (!cleanIsbn) {
            return;
        }

        post(route('books.borrow.exec'));
    };

    const cleanIsbn = normalizeIsbn(data.isbn);
    const isAlreadyBorrowed = myBorrowedIsbns.includes(cleanIsbn);
    const canSubmit =
        !processing &&
        cleanIsbn.length >= 10 &&
        (!locationRestricted || canBorrowHere) &&
        !isAlreadyBorrowed;

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col font-sans antialiased text-gray-900">
            <Head title="図書貸出" />

            <div className="bg-gray-800 px-6 py-5 flex justify-between items-center shadow-md">
                <div className="flex items-center space-x-3">
                    <span className="text-xl" aria-hidden="true">
                        📚
                    </span>
                    <h1 className="text-white font-black tracking-wider text-lg">
                        社内図書貸出カウンター
                    </h1>
                </div>
                <Link
                    href={route('dashboard')}
                    className="text-gray-300 text-sm font-black bg-gray-700 hover:bg-gray-600 hover:text-white px-5 py-2 rounded-xl transition-all shadow-sm"
                >
                    本棚へ戻る
                </Link>
            </div>

            <div className="flex-1 max-w-md w-full mx-auto px-4 py-8 space-y-6">
                <div className="flex items-center space-x-2 border-b border-gray-200 pb-3">
                    <span className="text-2xl" aria-hidden="true">
                        📖
                    </span>
                    <h2 className="font-black text-2xl text-gray-800 tracking-wide">図書を借りる</h2>
                </div>

                <FlashBanner rounded="rounded-xl" className="" />

                {isAlreadyBorrowed && cleanIsbn.length >= 10 && (
                    <div className="bg-amber-50 border-2 border-amber-200 text-amber-900 p-4 rounded-2xl text-sm font-bold leading-relaxed">
                        <p className="font-black">📌 すでに借用中の本です</p>
                        <p className="mt-1">
                            同じ本は同時に1冊までです。返却してから再度お借りください。
                        </p>
                    </div>
                )}

                {locationRestricted && !canBorrowHere && (
                    <div className="bg-amber-50 border-2 border-amber-200 text-amber-900 p-4 rounded-2xl text-sm font-bold leading-relaxed space-y-2">
                        <p className="font-black">📡 社外ネットワークから接続中</p>
                        <p>{locationBlockedMessage}</p>
                        {libraryLocation?.showDebug && (
                            <p className="text-xs text-amber-800/90">
                                接続元 IP:{' '}
                                <span className="font-mono">{libraryLocation?.currentIp ?? '—'}</span>
                                {' · '}
                                判定:{' '}
                                <span className="font-mono">{libraryLocation?.reason ?? '—'}</span>
                            </p>
                        )}
                    </div>
                )}

                {locationRestricted && canBorrowHere && (
                    <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-xl text-xs font-bold text-center">
                        {libraryLocation?.locationName ?? '社内Wi-Fi'} に接続されています。貸出できます。
                    </div>
                )}

                {locationRestricted && libraryLocation?.showDebug && (
                    <div className="bg-gray-100 border border-gray-200 text-gray-600 p-3 rounded-xl text-[11px] font-bold leading-relaxed space-y-1">
                        <p className="font-black text-gray-700">🧪 実験モード（local）</p>
                        <p>
                            接続元 IP:{' '}
                            <span className="font-mono">{libraryLocation?.currentIp ?? '—'}</span>
                        </p>
                        <p>
                            判定:{' '}
                            <span className="font-mono">{libraryLocation?.reason ?? '—'}</span>
                        </p>
                    </div>
                )}

                <div className="grid grid-cols-2 gap-2 p-1 bg-white border border-gray-200 rounded-2xl shadow-sm">
                    <button
                        type="button"
                        onClick={() => switchMode('manual')}
                        className={`py-3 rounded-xl text-sm font-black transition-all ${
                            inputMode === 'manual'
                                ? 'bg-gray-800 text-white shadow-sm'
                                : 'text-gray-500 hover:bg-gray-50'
                        }`}
                    >
                        手入力
                    </button>
                    <button
                        type="button"
                        onClick={() => switchMode('camera')}
                        className={`py-3 rounded-xl text-sm font-black transition-all ${
                            inputMode === 'camera'
                                ? 'bg-gray-800 text-white shadow-sm'
                                : 'text-gray-500 hover:bg-gray-50'
                        }`}
                    >
                        カメラスキャン
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    {inputMode === 'manual' ? (
                        <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm space-y-2">
                            <label
                                htmlFor="borrow-isbn"
                                className="block text-xs font-black text-gray-400 uppercase tracking-wider"
                            >
                                書籍のISBNコード
                            </label>
                            <input
                                id="borrow-isbn"
                                type="text"
                                inputMode="numeric"
                                value={data.isbn}
                                onChange={(e) => {
                                    setScanNotice('');
                                    setData('isbn', e.target.value);
                                }}
                                placeholder="例: 9784815608774"
                                className="w-full bg-gray-50 border border-gray-300 rounded-xl px-4 py-3 text-base text-gray-800 focus:border-gray-500 focus:outline-none font-bold shadow-inner"
                            />
                            <p className="text-xs text-gray-400 font-bold">
                                本の背表紙にある13桁のISBNを入力してください
                            </p>
                            <InputError message={errors.isbn} className="text-xs font-bold" />
                        </div>
                    ) : (
                        <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                            <IsbnBarcodeScanner onScan={handleIsbnDetected} />
                            {scanNotice && (
                                <p className="text-xs text-emerald-600 font-black bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center">
                                    {scanNotice}
                                </p>
                            )}
                            {data.isbn && (
                                <div className="text-center space-y-1">
                                    <p className="text-xs text-gray-400 font-black">読み取ったISBN</p>
                                    <p className="font-mono font-black text-lg text-gray-800">{data.isbn}</p>
                                </div>
                            )}
                            <InputError message={errors.isbn} className="text-xs font-bold" />
                        </div>
                    )}

                    <div className="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm space-y-3">
                        <span className="block text-xs font-black text-gray-400 uppercase tracking-wider">
                            貸出希望期間
                        </span>

                        <div className="grid grid-cols-3 gap-2 text-sm font-black">
                            <button
                                type="button"
                                onClick={() => setData('duration', '7')}
                                className={`py-3.5 rounded-xl border transition-all active:scale-95 ${
                                    data.duration === '7'
                                        ? 'bg-gray-800 border-gray-800 text-white shadow-md'
                                        : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'
                                }`}
                            >
                                1週間
                            </button>

                            <button
                                type="button"
                                onClick={() => setData('duration', '14')}
                                className={`py-3.5 rounded-xl border transition-all active:scale-95 ${
                                    data.duration === '14'
                                        ? 'bg-gray-800 border-gray-800 text-white shadow-md'
                                        : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'
                                }`}
                            >
                                2週間
                            </button>

                            <button
                                type="button"
                                onClick={() => setData('duration', '30')}
                                className={`py-3.5 rounded-xl border transition-all active:scale-95 ${
                                    data.duration === '30'
                                        ? 'bg-gray-800 border-gray-800 text-white shadow-md'
                                        : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'
                                }`}
                            >
                                1ヶ月
                            </button>
                        </div>
                    </div>

                    <div className="pt-2">
                        <button
                            type="submit"
                            disabled={!canSubmit}
                            className="w-full bg-[#00a0e9] border-b-4 border-[#007bbf] text-white font-black text-base py-4 rounded-2xl shadow-md transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {processing ? '貸出処理中...' : 'この条件で図書を借りる'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
