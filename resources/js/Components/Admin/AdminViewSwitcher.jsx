import { Link } from '@inertiajs/react';

export default function AdminViewSwitcher({ appearance = 'default' }) {
    const isGrafana = typeof route === 'function' && route().current('admin.grafana');
    const isDark = appearance === 'grafana';

    const base = isDark
        ? 'inline-flex rounded-lg border border-white/15 p-0.5 bg-black/30'
        : 'inline-flex rounded-lg border border-white/25 p-0.5 bg-white/10';

    const inactive = isDark
        ? 'px-3 py-1.5 rounded-md text-xs font-black tracking-wide text-white/70 hover:text-white hover:bg-white/10'
        : 'px-3 py-1.5 rounded-md text-xs font-black tracking-wide text-white/80 hover:text-white hover:bg-white/15';

    const menuActive = 'px-3 py-1.5 rounded-md text-xs font-black tracking-wide bg-white text-[#0069e5]';
    const grafanaActive = 'px-3 py-1.5 rounded-md text-xs font-black tracking-wide bg-[#ff9830] text-[#1b1206]';

    return (
        <div className={base} role="tablist" aria-label="管理画面の表示切替">
            <Link
                href={route('admin.menu')}
                role="tab"
                aria-selected={!isGrafana}
                className={!isGrafana ? menuActive : inactive}
            >
                メニュー
            </Link>
            <Link
                href={route('admin.grafana')}
                role="tab"
                aria-selected={isGrafana}
                className={isGrafana ? grafanaActive : inactive}
            >
                集計
            </Link>
        </div>
    );
}
