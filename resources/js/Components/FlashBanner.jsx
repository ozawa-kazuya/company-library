import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const SUCCESS_HIDE_MS = 5000;

export default function FlashBanner({
    className = 'mb-5',
    rounded = 'rounded-2xl',
    compact = false,
}) {
    const flash = usePage().props.flash ?? {};
    const error = flash.error_message || '';
    const success = flash.success_message || '';
    const message = error || success;
    const isError = Boolean(error);
    const [visible, setVisible] = useState(Boolean(message));
    const [visit, setVisit] = useState(0);

    useEffect(() => router.on('success', () => {
        setVisit((current) => current + 1);
    }), []);

    useEffect(() => {
        if (!message) {
            setVisible(false);
            return undefined;
        }

        setVisible(true);

        if (isError) {
            return undefined;
        }

        const timer = window.setTimeout(() => setVisible(false), SUCCESS_HIDE_MS);

        return () => window.clearTimeout(timer);
    }, [visit, message, isError]);

    if (!visible || !message) {
        return null;
    }

    return (
        <div
            role="status"
            className={`${isError ? 'bg-red-500' : 'bg-emerald-500'} text-white ${
                compact ? 'p-3 font-bold' : 'p-4 font-black'
            } ${rounded} text-center text-sm shadow-md animate-fadeIn ${className}`}
        >
            {message}
        </div>
    );
}
