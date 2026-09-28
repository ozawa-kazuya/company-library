import { Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

function StatPanel({ label, value, hint, color }) {
    return (
        <section className="bg-[#181b1f] border border-white/10 rounded-lg p-4 shadow-sm min-h-[7.5rem] flex flex-col justify-between">
            <p className="text-[11px] font-black uppercase tracking-wider text-white/55">{label}</p>
            <p className="text-4xl font-black tabular-nums leading-none" style={{ color }}>
                {value}
            </p>
            {hint && <p className="text-[11px] font-bold text-white/40">{hint}</p>}
        </section>
    );
}

function RankBars({ items, empty, barColor = '#5794f2' }) {
    const max = Math.max(...items.map((item) => item.count), 1);

    if (items.length === 0) {
        return <p className="text-sm font-bold text-white/40">{empty}</p>;
    }

    return (
        <ul className="space-y-2.5">
            {items.map((item) => (
                <li key={item.id ?? item.name}>
                    <div className="flex justify-between gap-3 text-[11px] font-black text-white/70 mb-1">
                        <span className="truncate min-w-0">
                            {item.name}
                            {item.hint ? (
                                <span className="ml-1 font-bold text-white/35">{item.hint}</span>
                            ) : null}
                        </span>
                        <span className="tabular-nums shrink-0">{item.count}</span>
                    </div>
                    <div className="h-2 rounded-full bg-white/10 overflow-hidden">
                        <div
                            className="h-full rounded-full"
                            style={{
                                width: `${Math.max(6, (item.count / max) * 100)}%`,
                                backgroundColor: barColor,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

function DayBars({ items, barColor = '#73bf69' }) {
    const max = Math.max(...items.map((item) => item.count), 1);

    return (
        <div className="flex items-end gap-1.5 h-40">
            {items.map((item) => {
                const height = item.count === 0 ? 4 : Math.max(10, (item.count / max) * 100);

                return (
                    <div key={item.day} className="flex-1 min-w-0 flex flex-col items-center justify-end h-full gap-1">
                        <span className="text-[10px] font-black text-white/50 tabular-nums">
                            {item.count > 0 ? item.count : ''}
                        </span>
                        <div
                            className="w-full max-w-[1.35rem] rounded-t-sm"
                            style={{ height: `${height}%`, backgroundColor: barColor }}
                            title={`${item.day}: ${item.count} 件`}
                        />
                        <span className="text-[9px] font-bold text-white/40 tabular-nums">{item.label}</span>
                    </div>
                );
            })}
        </div>
    );
}

function LoanList({ loans, empty, accent = '#f2495c' }) {
    if (loans.length === 0) {
        return <p className="text-sm font-bold text-white/40">{empty}</p>;
    }

    return (
        <ul className="divide-y divide-white/10">
            {loans.map((loan) => (
                <li
                    key={loan.id}
                    className="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1"
                >
                    <div className="min-w-0">
                        <p className="font-black text-sm text-white truncate">
                            {loan.book?.title ?? '不明な資料'}
                        </p>
                        <p className="text-[11px] font-bold text-white/45">
                            {loan.user?.name ?? '不明'}
                            {loan.user?.user_code ? `（${loan.user.user_code}）` : ''}
                        </p>
                    </div>
                    <p className="text-[11px] font-black tabular-nums shrink-0" style={{ color: accent }}>
                        期限 {loan.due_date ? new Date(loan.due_date).toLocaleDateString() : '—'}
                    </p>
                </li>
            ))}
        </ul>
    );
}

export default function Grafana({
    stats = {},
    categories = [],
    loansByDay = [],
    returnsByDay = [],
    topBooks = [],
    overdueLoans = [],
    dueSoonLoans = [],
}) {
    const booksTotal = stats.books_total ?? 0;
    const booksTitles = stats.books_titles ?? 0;
    const booksBorrowed = stats.books_borrowed ?? 0;
    const booksAvailable = stats.books_available ?? 0;
    const utilization = stats.utilization ?? 0;
    const overdue = stats.overdue ?? 0;
    const dueSoon = stats.due_soon ?? 0;
    const users = stats.users ?? 0;
    const activeBorrowers = stats.active_borrowers ?? 0;
    const loansToday = stats.loans_today ?? 0;
    const returnsToday = stats.returns_today ?? 0;
    const loansThisMonth = stats.loans_this_month ?? 0;

    const categoryItems = categories.map((item) => ({
        id: item.name,
        name: item.name,
        count: item.count,
    }));

    return (
        <AdminLayout
            title="集計"
            showBackLink={false}
            maxWidth="max-w-6xl"
            appearance="grafana"
        >
            <div className="space-y-5">
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
                    <div>
                        <p className="text-[11px] font-black tracking-[0.2em] text-[#ff9830]">
                            集計
                        </p>
                        <h2 className="text-2xl font-black text-white tracking-wide mt-1">
                            蔵書・貸出ダッシュボード
                        </h2>
                    </div>
                    <p className="text-xs font-bold text-white/45">
                        台帳からその場で集計しています。
                    </p>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <StatPanel label="蔵書" value={booksTotal} hint="登録冊数" color="#5794f2" />
                    <StatPanel label="種数" value={booksTitles} hint="ISBN の種類" color="#8ab8ff" />
                    <StatPanel label="保管中" value={booksAvailable} hint="貸出可能な冊数" color="#73bf69" />
                    <StatPanel label="貸出中" value={booksBorrowed} hint="未返却" color="#eab839" />
                    <StatPanel label="利用率" value={`${utilization}%`} hint="貸出中 ÷ 蔵書" color="#ff9830" />
                    <StatPanel label="期限切れ" value={overdue} hint="要フォロー" color="#f2495c" />
                    <StatPanel label="まもなく期限" value={dueSoon} hint="3 日以内" color="#ff9830" />
                    <StatPanel label="貸出中の人" value={activeBorrowers} hint="いま借りている人数" color="#b877d9" />
                    <StatPanel label="一般利用者" value={users} hint="管理者を除く" color="#8ab8ff" />
                    <StatPanel label="本日の貸出" value={loansToday} hint="借りた件数" color="#73bf69" />
                    <StatPanel label="本日の返却" value={returnsToday} hint="返した件数" color="#5794f2" />
                    <StatPanel label="今月の貸出" value={loansThisMonth} hint="今月借りた件数" color="#eab839" />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <section className="bg-[#181b1f] border border-white/10 rounded-lg p-4">
                        <h3 className="text-sm font-black text-white mb-4">カテゴリ別の蔵書</h3>
                        <RankBars items={categoryItems} empty="登録資料がありません。" barColor="#5794f2" />
                    </section>
                    <section className="bg-[#181b1f] border border-white/10 rounded-lg p-4">
                        <h3 className="text-sm font-black text-white mb-4">直近 30 日でよく借りられた資料</h3>
                        <RankBars items={topBooks} empty="この期間の貸出はありません。" barColor="#eab839" />
                    </section>
                    <section className="bg-[#181b1f] border border-white/10 rounded-lg p-4">
                        <h3 className="text-sm font-black text-white mb-4">直近 14 日の貸出件数</h3>
                        <DayBars items={loansByDay} barColor="#73bf69" />
                    </section>
                    <section className="bg-[#181b1f] border border-white/10 rounded-lg p-4">
                        <h3 className="text-sm font-black text-white mb-4">直近 14 日の返却件数</h3>
                        <DayBars items={returnsByDay} barColor="#5794f2" />
                    </section>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <section className="bg-[#181b1f] border border-white/10 rounded-lg p-4">
                        <div className="flex items-center justify-between gap-3 mb-4">
                            <h3 className="text-sm font-black text-white">返却期限切れ</h3>
                            <Link
                                href={route('admin.overdue')}
                                className="text-xs font-black text-[#8ab8ff] hover:underline"
                            >
                                一覧を開く
                            </Link>
                        </div>
                        <LoanList
                            loans={overdueLoans}
                            empty="期限切れの貸出はありません。"
                            accent="#f2495c"
                        />
                    </section>
                    <section className="bg-[#181b1f] border border-white/10 rounded-lg p-4">
                        <h3 className="text-sm font-black text-white mb-4">まもなく期限（3 日以内）</h3>
                        <LoanList
                            loans={dueSoonLoans}
                            empty="3 日以内に期限が来る貸出はありません。"
                            accent="#ff9830"
                        />
                    </section>
                </div>
            </div>
        </AdminLayout>
    );
}
