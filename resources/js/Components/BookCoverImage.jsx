import { useEffect, useMemo, useState } from 'react';
import { coverCandidateUrls, isLikelyPlaceholderImage } from '@/utils/bookCover';
import { rememberDisplayedCover } from '@/utils/rememberCover';

export default function BookCoverImage({
    isbn,
    cover,
    title = '',
    className = 'w-full h-full object-contain object-center',
    frameClassName = 'w-32 h-44 rounded-md shadow-lg border border-gray-200 overflow-hidden bg-gray-200',
    onDisplayUrl,
}) {
    const savedCover = String(cover ?? '').trim();
    const candidates = useMemo(() => coverCandidateUrls(isbn, cover), [isbn, cover]);
    const [index, setIndex] = useState(0);

    useEffect(() => {
        setIndex(0);
    }, [isbn, cover]);

    const coverSrc = candidates[index] ?? null;

    const tryNext = () => {
        setIndex((current) => current + 1);
    };

    const handleLoad = (event) => {
        if (event.target.src !== coverSrc && event.target.getAttribute('src') !== coverSrc) {
            return;
        }

        if (isLikelyPlaceholderImage(event.target)) {
            if (savedCover && coverSrc === savedCover) {
                return;
            }

            tryNext();
            return;
        }

        rememberDisplayedCover(isbn, coverSrc);
        onDisplayUrl?.(coverSrc);
    };

    const handleError = (event) => {
        if (event.target.src !== coverSrc && event.target.getAttribute('src') !== coverSrc) {
            return;
        }

        tryNext();
    };

    return (
        <div className={`relative ${frameClassName}`}>
            {coverSrc ? (
                <img
                    key={coverSrc}
                    src={coverSrc}
                    alt={`${title || '書籍'}の表紙`}
                    className={className}
                    onLoad={handleLoad}
                    onError={handleError}
                />
            ) : (
                <div className="absolute inset-0 flex flex-col justify-between p-2 text-center text-[9px] font-black text-white bg-gradient-to-br from-blue-500 to-indigo-700">
                    <div className="line-clamp-5 leading-tight">{title || '書籍タイトル'}</div>
                    {isbn ? (
                        <div className="text-[7px] opacity-80 truncate border-t border-white/20 pt-1 font-mono">
                            {isbn}
                        </div>
                    ) : null}
                </div>
            )}
        </div>
    );
}
