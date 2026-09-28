import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                bookPullOut: {
                    '0%': { transform: 'translateY(0) scale(1)' },
                    '45%': { transform: 'translateY(-14px) scale(1.1)' },
                    '100%': { transform: 'translateY(-10px) scale(1.06)' },
                },
                bookCardRise: {
                    '0%': { opacity: '0', transform: 'translateY(32px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                stampBounce: {
                    '0%, 100%': { transform: 'rotate(-12deg) scale(1)' },
                    '50%': { transform: 'rotate(-12deg) scale(1.12)' },
                },
                glowPulse: {
                    '0%, 100%': { boxShadow: '0 0 0 0 rgba(16, 185, 129, 0.35)' },
                    '50%': { boxShadow: '0 0 18px 4px rgba(16, 185, 129, 0.55)' },
                },
            },
            animation: {
                fadeIn: 'fadeIn 0.3s ease-out',
                bookPullOut: 'bookPullOut 0.35s ease-out forwards',
                bookCardRise: 'bookCardRise 0.35s ease-out forwards',
                stampBounce: 'stampBounce 0.45s ease-in-out 2',
                glowPulse: 'glowPulse 1.6s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
