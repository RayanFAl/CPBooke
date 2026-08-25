import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Admin design tokens (UX-01):
 * - brand.*     → accent / primary actions (cyan)
 * - surface.*   → page, card, sidebar backgrounds
 * - status.*    → semantic feedback colors
 * - radius.admin → consistent border radii for admin UI
 *
 * Prefer these tokens in new admin components over raw slate/cyan utilities.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#0891b2',
                    50: '#ecfeff',
                    100: '#cffafe',
                    400: '#22d3ee',
                    600: '#0891b2',
                    700: '#0e7490',
                    800: '#155e75',
                },
                surface: {
                    page: '#f1f5f9',
                    card: '#ffffff',
                    sidebar: '#020617',
                    muted: '#f8fafc',
                },
                status: {
                    success: '#059669',
                    'success-bg': '#d1fae5',
                    warning: '#d97706',
                    'warning-bg': '#fef3c7',
                    error: '#e11d48',
                    'error-bg': '#ffe4e6',
                    info: '#0284c7',
                    'info-bg': '#e0f2fe',
                },
            },
            borderRadius: {
                'admin-sm': '0.75rem',
                'admin-md': '1rem',
                'admin-lg': '1.5rem',
            },
        },
    },

    plugins: [forms],
};
