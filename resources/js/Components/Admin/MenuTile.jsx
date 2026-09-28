import { Link } from '@inertiajs/react';

const tileBaseClass =
    'aspect-[4/3] rounded-xl flex flex-col justify-center items-center border relative overflow-hidden';

export default function MenuTile({ id, title, icon, bg, href, enabled, badge }) {
    if (enabled) {
        return (
            <Link
                href={href}
                className={`${bg} ${tileBaseClass} text-white shadow-md hover:shadow-xl transition-all border-black/10 group active:scale-[0.97]`}
                aria-label={title}
            >
                <div
                    className="absolute inset-0 bg-black/0 group-hover:bg-white/10 transition-all"
                    aria-hidden="true"
                />
                {badge != null && badge > 0 && (
                    <span
                        className="absolute top-2 right-2 min-w-[1.5rem] px-1.5 py-0.5 bg-red-600 text-white text-[10px] font-black rounded-full shadow-md z-10"
                        aria-label={`期限切れ ${badge} 件`}
                    >
                        {badge}
                    </span>
                )}
                <span
                    className="text-2xl md:text-3xl block group-hover:scale-110 transition-all duration-300"
                    aria-hidden="true"
                >
                    {icon}
                </span>
                <span className="text-xs md:text-sm font-black tracking-wide mt-2 md:mt-3 text-center px-2 line-clamp-1">
                    {title}
                </span>
            </Link>
        );
    }

    return (
        <div
            id={id}
            role="group"
            aria-disabled="true"
            aria-label={`${title}（準備中）`}
            className={`${bg} ${tileBaseClass} text-white/40 shadow-sm border-black/5 opacity-85 cursor-not-allowed saturate-[0.6] brightness-95`}
        >
            <span className="text-2xl md:text-3xl block grayscale" aria-hidden="true">
                {icon}
            </span>
            <span className="text-xs md:text-sm font-bold tracking-wide mt-2 md:mt-3 text-center px-2 line-clamp-1">
                {title}
            </span>
            <div
                className="absolute top-1 right-2 text-[9px] font-black tracking-wider bg-black/20 text-white/70 px-1.5 py-0.5 rounded-md scale-75"
                aria-hidden="true"
            >
                準備中
            </div>
            <span className="sr-only">準備中</span>
        </div>
    );
}
