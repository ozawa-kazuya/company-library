import { Link } from '@inertiajs/react';

export default function AdminMenuButton({ title, href, enabled = true, badge, download = false }) {
    if (!enabled) {
        return (
            <div
                className="w-full bg-gray-200 text-gray-400 font-bold text-base py-4 rounded-lg text-center cursor-not-allowed"
                aria-disabled="true"
            >
                {title}
                <span className="ml-2 text-xs font-black">（準備中）</span>
            </div>
        );
    }

    const className =
        'relative block w-full bg-[#0069e5] hover:bg-[#005bcc] text-white font-bold text-base py-4 rounded-lg text-center shadow-sm transition-all active:scale-[0.99]';

    const content = (
        <>
            {title}
            {badge != null && badge > 0 && (
                <span className="absolute top-2 right-3 min-w-[1.5rem] px-1.5 py-0.5 bg-red-600 text-white text-[10px] font-black rounded-full">
                    {badge}
                </span>
            )}
        </>
    );

    if (download) {
        return (
            <a href={href} className={className}>
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={className}>
            {content}
        </Link>
    );
}
