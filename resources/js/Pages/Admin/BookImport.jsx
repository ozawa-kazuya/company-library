import { Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import InputError from '@/Components/InputError';

export default function BookImport() {
    const { flash } = usePage().props;

    const { data, setData, post, processing, errors, progress } = useForm({
        file: null,
        lookup_missing_isbn: false,
        overwrite_existing: false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.books.import.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Excel入出力" maxWidth="max-w-2xl">
            <div className="space-y-6">
                <div className="flex items-center justify-between gap-3 border-b border-gray-200 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl" aria-hidden="true">
                            📊
                        </span>
                        <h2 className="font-black text-2xl text-gray-800 tracking-wide">
                            Excel入出力
                        </h2>
                    </div>
                    <Link
                        href={route('admin.books.create')}
                        className="text-xs font-black text-[#00a0e9] hover:underline shrink-0"
                    >
                        1冊ずつ登録 →
                    </Link>
                </div>

                {Array.isArray(flash?.import_warnings) && flash.import_warnings.length > 0 && (
                    <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-2">
                        <p className="text-xs font-black text-amber-800">注意事項（最大20件）</p>
                        <ul className="text-xs text-amber-900 space-y-1 list-disc pl-4">
                            {flash.import_warnings.map((warning) => (
                                <li key={warning}>{warning}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <a
                    href={route('admin.books.export')}
                    className="flex items-center justify-between gap-3 bg-emerald-50 border-2 border-[#00897b]/30 rounded-2xl px-4 py-4 transition-all hover:bg-emerald-100 hover:border-[#00897b]/50"
                >
                    <div className="flex items-center gap-3 min-w-0">
                        <span className="text-2xl shrink-0" aria-hidden="true">
                            💾
                        </span>
                        <div className="min-w-0">
                            <p className="font-black text-sm text-gray-800">いまの台帳をExcelで保存</p>
                            <p className="text-xs text-gray-500 font-bold">
                                一括登録と同じ列（題名・ISBN・カテゴリ・在庫数・表紙）です
                            </p>
                        </div>
                    </div>
                    <span className="text-xs font-black text-[#00897b] shrink-0">ダウンロード →</span>
                </a>

                <div className="pt-2">
                    <h3 className="text-sm font-black text-gray-500 uppercase tracking-wider mb-3 border-b border-gray-200 pb-2">
                        Excelから一括登録
                    </h3>
                </div>

                <div className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm space-y-3 text-sm text-gray-700">
                    <p className="font-black text-gray-800">対応ファイル形式</p>
                    <ul className="list-disc pl-5 space-y-1 text-xs font-bold text-gray-600">
                        <li>Excel（.xlsx）</li>
                        <li>CSV（.csv）</li>
                    </ul>
                    <p className="font-black text-gray-800 pt-2">列の例</p>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-xs border border-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="border border-gray-200 px-2 py-1 text-left">題名</th>
                                    <th className="border border-gray-200 px-2 py-1 text-left">ISBN</th>
                                    <th className="border border-gray-200 px-2 py-1 text-left">カテゴリ</th>
                                    <th className="border border-gray-200 px-2 py-1 text-left">在庫数</th>
                                    <th className="border border-gray-200 px-2 py-1 text-left">表紙</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td className="border border-gray-200 px-2 py-1">独習PHP</td>
                                    <td className="border border-gray-200 px-2 py-1">9784798168494</td>
                                    <td className="border border-gray-200 px-2 py-1">PHP・Laravel</td>
                                    <td className="border border-gray-200 px-2 py-1">2</td>
                                    <td className="border border-gray-200 px-2 py-1">https://example.com/cover.jpg</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p className="text-xs text-gray-500 font-bold">
                        「題名」は必須です。ISBN が空の場合は仮ISBN（9789999...）で登録されます。
                        <strong>在庫数</strong>の冊数だけが登録されます（列が空の場合は1冊）。
                        検索で表紙が出ないときは <strong>表紙</strong> 列に画像URLを貼ると、一括登録ですぐ表示されます。同じISBNの追加冊も同じ表紙になります。
                        会社の在庫管理Excel（題名・在庫数）にも対応しています。
                        Excel から「名前を付けて保存」で <strong>.xlsx</strong> 形式での保存を推奨します（.csv でも可）。
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                        <div>
                            <label
                                htmlFor="import-file"
                                className="block text-xs font-black text-gray-400 uppercase tracking-wider mb-2"
                            >
                                インポートファイル
                            </label>
                            <input
                                id="import-file"
                                type="file"
                                accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                onChange={(e) => setData('file', e.target.files[0] ?? null)}
                                className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:font-black file:text-gray-700 hover:file:bg-gray-200"
                            />
                            <InputError message={errors.file} className="mt-2 text-xs font-bold" />
                            {progress && (
                                <p className="mt-2 text-xs font-bold text-[#00a0e9]">
                                    アップロード中… {progress.percentage}%
                                </p>
                            )}
                        </div>

                        <label className="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.lookup_missing_isbn}
                                onChange={(e) => setData('lookup_missing_isbn', e.target.checked)}
                                className="mt-1 rounded border-gray-300"
                            />
                            <span className="text-xs font-bold text-gray-700">
                                ISBN が空の行を国立国会図書館・Google Books で自動検索する
                                <span className="block text-gray-400 font-normal mt-1">
                                    件数が多いと時間がかかります（1件あたり約1秒）。
                                </span>
                            </span>
                        </label>

                        <label className="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.overwrite_existing}
                                onChange={(e) => setData('overwrite_existing', e.target.checked)}
                                className="mt-1 rounded border-gray-300"
                            />
                            <span className="text-xs font-bold text-gray-700">
                                既存資料を上書き登録する
                                <span className="block text-gray-400 font-normal mt-1">
                                    チェック時は<strong>保管中の資料を置き換え</strong>ます。Excel の内容が台帳になり、Excel に無い保管中の資料は削除されます。
                                    貸出中の冊と貸出状況はそのまま残ります。
                                </span>
                            </span>
                        </label>
                    </div>

                    <button
                        type="submit"
                        disabled={processing || !data.file}
                        className="w-full bg-[#00a0e9] border-b-4 border-[#007bbf] text-white font-black text-base py-4 rounded-2xl shadow-md transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {processing ? '一括登録中…' : 'ファイルから一括登録する'}
                    </button>
                </form>
            </div>
        </AdminLayout>
    );
}
