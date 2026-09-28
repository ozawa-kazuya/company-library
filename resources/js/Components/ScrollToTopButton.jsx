import { useEffect, useState } from 'react';

export default function ScrollToTopButton() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const onScroll = () => {
            setVisible(window.scrollY > 280);
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            className="fixed bottom-6 right-5 z-[60] px-4 py-3 rounded-2xl bg-[#0069e5] border-b-4 border-[#0050b3] text-white text-xs font-black shadow-lg hover:bg-[#0057cc] active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
        >
            上部へ
        </button>
    );
}
