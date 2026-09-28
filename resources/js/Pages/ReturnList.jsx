import { Head, useForm, Link } from '@inertiajs/react';
import FlashBanner from '@/Components/FlashBanner';

export default function ReturnList({ myLoans }) {
    const { post, processing } = useForm();

    // 「返却」ボタンを押したときの処理
    const handleReturnSubmit = (bookId, bookTitle) => {
        if (confirm(`「${bookTitle}」を返却しますか？`)) {
            post(route('books.return', bookId), {
                preserveScroll: true, // 画面の位置をキープしたまま更新
            });
        }
    };

    return (
        <div className="min-h-screen bg-gray-100 max-w-md mx-auto shadow-xl relative flex flex-col font-sans select-none antialiased">
            <Head title="現在の貸出中一覧（返却）" />

            {/* 🟦 インダストリアル風ヘッダー */}
            <div className="bg-[#434343] p-4 flex items-center justify-between shadow-md border-b-4 border-[#2b2b2b]">
                <h1 className="text-white font-black tracking-wider text-base">🔄 現在の貸出中一覧</h1>
                <Link href={route('dashboard')} className="text-white text-xs bg-gray-700 px-3 py-1 rounded-md font-bold border border-gray-600">
                    戻る
                </Link>
            </div>

            {/* 🎯 メインエリア */}
            <div className="p-4 flex-1 flex flex-col pb-12">

                <FlashBanner compact rounded="rounded-xl" className="mb-4" />

                {/* 📋 自分が今借りている本の一覧リスト */}
                {myLoans.length === 0 ? (
                    /* 1冊も借りていない場合のクリーンビュー */
                    <div className="text-center py-16 bg-white rounded-2xl border-4 border-dashed border-gray-300 px-6 my-auto">
                        <span className="text-5xl block mb-3">🎉</span>
                        <h3 className="font-black text-gray-800 text-lg mb-1">借りている本はありません</h3>
                        <p className="text-xs text-gray-400 font-bold">すべての書籍の返却が完了しています。</p>
                    </div>
                ) : (
                    /* 借りている本がある場合の縦並びリスト */
                    <div className="space-y-3">
                        <p className="text-xs text-gray-500 font-bold pl-1 uppercase tracking-wider">
                            📊 貸出中の書籍（計 {myLoans.length} 冊）
                        </p>

                        {myLoans.map((loan) => {
                            const book = loan.book;
                            return (
                                <div
                                    key={loan.id}
                                    className="bg-white p-3 rounded-xl shadow-sm border-2 border-gray-200 flex gap-3 items-center justify-between hover:border-gray-300 transition-all"
                                >
                                    {/* 左側：本のアイコンサムネイル風表現 */}
                                    <div className="w-12 h-16 bg-gradient-to-br from-gray-400 to-gray-500 rounded-lg flex items-center justify-center p-1 text-[8px] font-bold text-white text-center flex-shrink-0 shadow-sm border border-gray-300">
                                        <span className="line-clamp-3">{book.title}</span>
                                    </div>

                                    {/* 中央：本のテキスト情報 */}
                                    <div className="flex-1 min-w-0 pl-1">
                                        <h4 className="font-black text-sm text-gray-900 truncate leading-snug">{book.title}</h4>
                                        <p className="text-[9px] text-sky-600 font-mono mt-1 font-bold">
                                            📅 {new Date(loan.borrowed_at).toLocaleDateString()}〜
                                        </p>
                                    </div>

                                    {/* 右側：無骨でデカくて押しやすい「返却ボタン」 */}
                                    <button
                                        onClick={() => handleReturnSubmit(book.id, book.title)}
                                        disabled={processing}
                                        className="bg-[#434343] border-b-4 border-[#2b2b2b] hover:bg-gray-700 text-white font-black text-xs px-4 py-2.5 rounded-xl active:scale-95 transition-all flex-shrink-0 shadow-sm disabled:opacity-50"
                                    >
                                        返却
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}

            </div>
        </div>
    );
}
