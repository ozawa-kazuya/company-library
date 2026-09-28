import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';

export default function BookSearch() {
    // 📝 画面で管理する状態（キーワード、検索結果、通信中のぐるぐるフラグ）
    const [keyword, setKeyword] = useState('');
    const [books, setBooks] = useState([]);
    const [loading, setLoading] = useState(false);

    // 🔍 Google Books APIを叩いて本物のデータを取得するメイン関数
    const handleSearchSubmit = async (e) => {
        e.preventDefault();
        if (!keyword.trim()) return;

        setLoading(true);

        // 1. 🔑 キーワードをURLで使用できる安全な文字コードにエンコード
        const encodeKeyword = encodeURIComponent(keyword);

        // 2. ⭕️ 修正ポイント：文字列の足し算を完璧に繋ぎ、正しい検索APIエンドポイントを作成します
        const url = `https://www.googleapis.com/books/v1/volumes?q=${encodeKeyword}&maxResults=9`;

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error("API通信エラー");

            const data = await response.json();

            // 3. 検索結果（items）が空っぽでなければ配列をセット、空なら空配列にする
            setBooks(data.items || []);
        } catch (error) {
            console.error("書籍データの取得に失敗しました:", error);
            alert("データの取得に失敗しました。時間をおいて再度お試しください。");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col font-sans select-none antialiased text-gray-900">
            <Head title="書籍検索・表紙自動取得" />

            {/* 🟦 トップバー */}
            <div className="bg-[#0098da] px-6 py-5 flex justify-between items-center shadow-md">
                <h1 className="text-white font-black tracking-wider text-xl">🔍 書籍マスター検索システム</h1>
                <Link href="/dashboard" className="text-white text-sm bg-sky-700/50 px-5 py-2 rounded-xl font-black transition-all hover:bg-sky-800">
                    本棚に戻る
                </Link>
            </div>

            {/* 🎯 メインレイアウトエリア */}
            <div className="flex-1 max-w-4xl mx-auto w-full px-4 py-8 space-y-8">

                {/* 💳 検索入力フォームカード（ゆったり大画面仕様） */}
                <div className="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 max-w-2xl mx-auto">
                    <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-4">
                        <div className="flex-1">
                            <input
                                type="text"
                                value={keyword}
                                onChange={(e) => setKeyword(e.target.value)}
                                placeholder="本のタイトル、またはISBNを入力..."
                                className="w-full bg-white border border-gray-300 rounded-xl px-5 py-3.5 text-base text-gray-800 placeholder-gray-400 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 focus:outline-none shadow-sm transition-all"
                                required
                                autoFocus
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={loading}
                            className="bg-[#00a0e9] border-b-4 border-[#007bbf] hover:bg-sky-500 text-white font-black px-8 py-3.5 rounded-xl text-base shadow transition-all active:scale-[0.98] flex items-center justify-center tracking-widest min-w-[140px]"
                        >
                            {loading ? '検索中...' : '検索する'}
                        </button>
                    </form>
                </div>

                {/* 🌀 読み込み中のぐるぐるアニメーション */}
                {loading && (
                    <div className="flex justify-center py-12">
                        <div className="animate-spin h-10 w-10 border-4 border-[#00a0e9] border-t-transparent rounded-full"></div>
                    </div>
                )}

                {/* 📚 検索結果：画像がズラリと綺麗に並ぶ3列の本棚風グリッドシステム */}
                {!loading && books.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                        {books.map((item) => {
                            // 🔍 JSONの深い階層から、画像（thumbnail）の文字列URLを安全に探索して抽出
                            const rawThumbnail = item?.volumeInfo?.imageLinks?.thumbnail || item?.volumeInfo?.imageLinks?.smallThumbnail;

                            // ⚠️ セキュリティ対策：Googleから戻る画像URLは「http」なので、ブラウザにブロックされないよう「https」に一発で自動置換！
                            const secureThumbnail = rawThumbnail ? rawThumbnail.replace("http://", "https://") : null;

                            const title = item?.volumeInfo?.title || 'タイトル不明';
                            const isbn = item?.volumeInfo?.industryIdentifiers?.[0]?.identifier || 'ISBNなし';

                            return (
                                <div key={item.id} className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all flex flex-col items-center text-center justify-between space-y-4">

                                    {/* 🎨 本物の表紙画像を <img> の src にダイレクト指定して美しく描画 */}
                                    <div className="w-28 h-36 bg-gray-100 rounded-md shadow-md overflow-hidden border flex-shrink-0 flex items-center justify-center relative bg-gradient-to-br from-gray-50 to-gray-100">
                                        {secureThumbnail ? (
                                            <img
                                                src={secureThumbnail}
                                                alt={title}
                                                className="w-full h-full object-cover animate-fadeIn"
                                                onError={(e) => {
                                                    // 画像リンクが切れていた場合の保険処理
                                                    e.target.style.display = 'none';
                                                }}
                                            />
                                        ) : null}

                                        {/* 万が一、Google側に画像データが全く無かった場合の文字デザイン保険 */}
                                        <div className="absolute inset-0 p-2 flex flex-col justify-between bg-gradient-to-br from-blue-500 to-indigo-700 text-white text-[9px] font-black z-0 pointer-events-none">
                                            <div className="line-clamp-4">{title}</div>
                                            <div className="text-[7px] border-t border-white/20 pt-1 truncate font-mono">{isbn}</div>
                                        </div>
                                    </div>

                                    {/* 書籍のテキスト情報（大きく、読みやすく調整） */}
                                    <div className="flex-1 w-full space-y-1 min-w-0">
                                        <h3 className="font-black text-sm text-gray-900 truncate leading-snug" title={title}>
                                            {title}
                                        </h3>
                                        <p className="text-[10px] bg-gray-100 text-gray-500 font-mono font-bold px-2 py-0.5 rounded inline-block">
                                            ISBN: {isbn}
                                        </p>
                                    </div>

                                    {/* 🏢 現場の手間を0にする：スキャンせずにこのデータでマスター登録するボタン（拡張用） */}
                                    <button className="w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 rounded-xl text-xs transition-all active:scale-95 shadow-sm">
                                        この本を台帳に登録
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* 📭 検索結果が1件もヒットしなかった場合の案内 */}
                {!loading && keyword && books.length === 0 && (
                    <div className="text-center py-16 bg-white rounded-2xl border-4 border-dashed border-gray-300 px-6 max-w-2xl mx-auto">
                        <span className="text-5xl block mb-3">❓</span>
                        <h3 className="font-black text-gray-700 text-base">該当する書籍が見つかりませんでした</h3>
                        <p className="text-xs text-gray-400 font-bold mt-1">キーワードの打ち間違いがないか、またはISBN番号でお試しください。</p>
                    </div>
                )}

            </div>
        </div>
    );
}
