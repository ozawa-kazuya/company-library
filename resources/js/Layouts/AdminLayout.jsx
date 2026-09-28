import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import AdminViewSwitcher from '@/Components/Admin/AdminViewSwitcher';
import FlashBanner from '@/Components/FlashBanner';
import ScrollToTopButton from '@/Components/ScrollToTopButton';

export default function AdminLayout({
    title,
    showBackLink = false,
    backLabel = 'ダッシュボード',
    maxWidth = 'max-w-3xl',
    appearance = 'default',
    children,
}) {
    const page = usePage();
    const user = page.props.auth?.user;
    const mustChangePassword = Boolean(page.props.mustChangePassword);
    const canLeave = !mustChangePassword;
    const isGrafana = appearance === 'grafana';
    const headerRef = useRef(null);

    useEffect(() => {
        const el = headerRef.current;

        if (!el || typeof ResizeObserver === 'undefined') {
            return undefined;
        }

        const apply = () => {
            document.documentElement.style.setProperty(
                '--admin-header-h',
                `${el.offsetHeight}px`,
            );
        };

        apply();
        const observer = new ResizeObserver(apply);
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            className={`min-h-screen flex flex-col font-sans antialiased ${
                isGrafana ? 'bg-[#111217] text-gray-100' : 'bg-gray-100 text-gray-900'
            }`}
        >
            <Head title={title} />

            <header
                ref={headerRef}
                className={`px-6 md:px-8 py-5 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sticky top-0 z-50 ${
                    isGrafana
                        ? 'bg-[#181b1f] border-b border-white/10 shadow-none'
                        : 'bg-[#0069e5] shadow-md'
                }`}
            >
                <h1 className="text-white font-black tracking-wider text-lg">
                    図書貸出管理システム
                </h1>

                <nav className="flex flex-wrap items-center gap-4 sm:gap-6 text-sm font-bold text-white">
                    {canLeave && <AdminViewSwitcher appearance={appearance} />}
                    {user?.name && (
                        <span className="text-white/80 hidden md:inline">{user.name}</span>
                    )}
                    {canLeave && (
                        <Link
                            href={route('admin.password')}
                            className="hover:underline underline-offset-4"
                        >
                            パスワード変更
                        </Link>
                    )}
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="hover:underline underline-offset-4"
                    >
                        ログアウト
                    </Link>
                </nav>
            </header>

            <main className={`flex-1 w-full mx-auto px-4 py-8 ${maxWidth}`}>
                {canLeave && showBackLink && (
                    <div className="mb-4">
                        <Link
                            href={route('admin.menu')}
                            className="text-sm font-bold text-[#0069e5] hover:underline"
                        >
                            ← {backLabel}
                        </Link>
                    </div>
                )}
                <FlashBanner rounded="rounded-xl" />
                {children}
            </main>

            <ScrollToTopButton />

            <footer
                className={`py-6 text-center text-xs font-bold tracking-wide ${
                    isGrafana ? 'text-white/35' : 'text-gray-400'
                }`}
            >
                © 2026 株式会社エプコットソフトウェア
            </footer>
        </div>
    );
}
