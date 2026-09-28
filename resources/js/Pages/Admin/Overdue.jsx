import AdminLayout from '@/Layouts/AdminLayout';
import { getLoanDisplayInfo, getUserEmpId } from '@/utils/loanDisplay';
import { getOverdueAmount } from '@/utils/overdueDisplay';

function OverdueRow({ loan }) {
    const loanInfo = getLoanDisplayInfo(loan);
    const overdue = getOverdueAmount(loan);
    const dueDate = loan.due_date ? new Date(loan.due_date) : null;

    return (
        <>
            <div className="hidden md:grid md:grid-cols-12 px-6 py-6 items-center bg-red-50/10 hover:bg-red-50/30 transition-all">
                <div className="col-span-4 pr-4 space-y-0.5">
                    <h4 className="font-black text-base text-gray-900 truncate">
                        {loan.book?.title || '削除された書籍'}
                    </h4>
                    <p className="text-xs text-gray-400 font-bold font-mono">
                        ISBN: {loan.book?.isbn || '—'}
                        {loan.book?.copy_number > 1 && (
                            <span className="ml-2 text-[#00a0e9]">第{loan.book.copy_number}冊</span>
                        )}
                    </p>
                </div>

                <div className="col-span-2 pl-2">
                    <span className="px-3 py-1 bg-gray-100 text-gray-600 font-black text-xs rounded-lg border border-gray-200/60">
                        {loan.book?.category || '未分類'}
                    </span>
                </div>

                <div className="col-span-3 flex flex-col items-start justify-center space-y-1 pl-6">
                    <span className="inline-block px-2.5 py-0.5 text-[11px] bg-red-600 text-white border border-red-600 font-black animate-pulse rounded-md shadow-sm">
                        期限切れ {overdue?.text}
                    </span>
                    <p className="font-black text-sm text-gray-800">
                        {loan.user?.name || '不明な社員'}
                    </p>
                    <p className="text-[10px] text-gray-400 font-bold font-mono">
                        {loan.user ? getUserEmpId(loan.user) : '—'}
                    </p>
                </div>

                <div className="col-span-3 flex flex-col items-end justify-center space-y-1 pr-4">
                    <span className="px-5 py-1.5 border rounded-full font-black text-sm shadow-inner bg-red-100 text-red-600 border-red-300 animate-pulse">
                        {loanInfo?.badgeText ?? '—'}
                    </span>
                    {dueDate && (
                        <div className="text-right text-sm font-black text-gray-700 space-y-0.5">
                            {loanInfo?.borrowedAtText && (
                                <div>貸出: {loanInfo.borrowedAtText}</div>
                            )}
                            <div className="text-red-600">
                                返却期限: {dueDate.toLocaleDateString()}
                            </div>
                        </div>
                    )}
                    {loanInfo?.durationLabel && (
                        <p className="text-xs text-gray-400 font-bold">{loanInfo.durationLabel}</p>
                    )}
                </div>
            </div>

            <div className="md:hidden px-4 py-4 space-y-3 bg-red-50/20">
                <div className="flex justify-between items-start gap-2">
                    <div className="min-w-0">
                        <h4 className="font-black text-base text-gray-900 truncate">
                            {loan.book?.title || '削除された書籍'}
                        </h4>
                        <p className="text-xs text-gray-400 font-mono font-bold">
                            ISBN: {loan.book?.isbn || '—'}
                            {loan.book?.copy_number > 1 && (
                                <span className="ml-2 text-[#00a0e9]">第{loan.book.copy_number}冊</span>
                            )}
                        </p>
                    </div>
                    <span className="shrink-0 px-2 py-1 bg-red-600 text-white text-[10px] font-black rounded-lg animate-pulse">
                        {overdue?.text}
                    </span>
                </div>
                <div className="flex flex-wrap gap-2 text-xs font-black">
                    <span className="px-2 py-1 bg-gray-100 text-gray-600 rounded-lg">
                        {loan.book?.category || '未分類'}
                    </span>
                    <span className="px-2 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg">
                        {loan.user?.name || '不明'}
                    </span>
                </div>
                {loanInfo?.borrowedAtText && (
                    <p className="text-xs font-bold text-gray-600">
                        貸出: {loanInfo.borrowedAtText}
                    </p>
                )}
            </div>
        </>
    );
}

export default function Overdue({ overdueLoans = [] }) {
    const loans = overdueLoans || [];

    return (
        <AdminLayout title="返却期限切れ一覧">
            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 border-b border-gray-200 pb-3">
                    <div className="flex items-center space-x-2">
                        <span className="text-2xl" aria-hidden="true">
                            🚨
                        </span>
                        <h2 className="font-black text-2xl text-red-600 tracking-wide">
                            返却期限切れ一覧
                        </h2>
                    </div>
                    <span className="px-4 py-2 bg-red-600 text-white font-black text-sm rounded-2xl shadow-md self-start sm:self-auto">
                        要督促: {loans.length} 冊
                    </span>
                </div>

                {loans.length === 0 ? (
                    <div className="text-center py-20 bg-white rounded-3xl border-4 border-dashed border-gray-200 px-6">
                        <span className="text-5xl block mb-4" aria-hidden="true">
                            ✅
                        </span>
                        <h3 className="font-black text-emerald-600 text-lg">
                            返却期限を過ぎている書籍はありません
                        </h3>
                        <p className="text-xs text-gray-400 font-bold mt-2">
                            すべての社員が期限内に返却しています
                        </p>
                    </div>
                ) : (
                    <div className="bg-white border-2 border-red-200 rounded-3xl shadow-xl overflow-hidden">
                        <div className="hidden md:grid md:grid-cols-12 bg-red-50/60 px-6 py-4 border-b border-red-100 text-xs font-black text-red-700 uppercase tracking-wider">
                            <div className="col-span-4 pl-1">書籍情報</div>
                            <div className="col-span-2 pl-2">ジャンル</div>
                            <div className="col-span-3 pl-6">借用者（督促対象）</div>
                            <div className="col-span-3 text-right pr-4">超過状況</div>
                        </div>

                        <div className="divide-y divide-red-100">
                            {loans.map((loan) => (
                                <OverdueRow key={loan.id} loan={loan} />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
