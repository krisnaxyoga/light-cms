/**
 * Tailwind CSS 3 + daisyUI 4 for the LightCMS admin UI.
 *
 * daisyUI 4 is paired with Tailwind 3 on purpose (daisyUI 5 needs Tailwind 4);
 * this is the same pairing the JHG WordPress themes use.
 *
 * Build: `npm run build:css` -> public/assets/admin/css/admin-ui.css (committed,
 * so running LightCMS itself still needs no build step).
 */
module.exports = {
    content: [
        './app/Views/admin/**/*.php',
        './app/Helpers/*.php',
        './app/Libraries/WordPress/api/*.php',
        './public/assets/admin/js/admin.js',
    ],

    // The block editor screen (app/Views/admin/posts/form.php) is deliberately
    // NOT in `content`: it ships its own hand-written CSS and its 1.8k lines of
    // JS bind to those class names, so Tailwind's preflight must not touch it.

    theme: {
        extend: {
            fontFamily: {
                sans: ['system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
            },
        },
    },

    plugins: [require('daisyui')],

    // Classes assembled at runtime (status/score/notice types) never appear
    // literally in the source, so they are listed here.
    safelist: [
        'alert-success', 'alert-error', 'alert-warning', 'alert-info',
        'badge-success', 'badge-error', 'badge-warning', 'badge-info',
        'badge-neutral', 'badge-ghost', 'badge-outline',
    ],

    daisyui: {
        themes: [
            {
                // Light: keeps the palette LightCMS already shipped
                // (#3498db primary, #f4f6f8 canvas, #24292e text).
                lightcms: {
                    'primary': '#3498db',
                    'primary-content': '#ffffff',
                    'secondary': '#64748b',
                    'secondary-content': '#ffffff',
                    'accent': '#0ea5e9',
                    'accent-content': '#ffffff',
                    'neutral': '#24292e',
                    'neutral-content': '#ffffff',
                    'base-100': '#ffffff',
                    'base-200': '#f4f6f8',
                    'base-300': '#e1e4e8',
                    'base-content': '#24292e',
                    'info': '#0ea5e9',
                    'info-content': '#ffffff',
                    'success': '#2f9e6d',
                    'success-content': '#ffffff',
                    'warning': '#b45309',
                    'warning-content': '#ffffff',
                    'error': '#e74c3c',
                    'error-content': '#ffffff',
                    '--rounded-box': '0.5rem',
                    '--rounded-btn': '0.375rem',
                    '--rounded-badge': '0.375rem',
                    '--animation-btn': '0.15s',
                    '--border-btn': '1px',
                },
            },
            {
                lightcmsdark: {
                    'primary': '#58a6ff',
                    'primary-content': '#0b1622',
                    'secondary': '#8b949e',
                    'secondary-content': '#0b1622',
                    'accent': '#38bdf8',
                    'accent-content': '#0b1622',
                    'neutral': '#1f2630',
                    'neutral-content': '#e6edf3',
                    'base-100': '#12161c',
                    'base-200': '#171c24',
                    'base-300': '#232a35',
                    'base-content': '#e6edf3',
                    'info': '#38bdf8',
                    'info-content': '#0b1622',
                    'success': '#34d399',
                    'success-content': '#0b1622',
                    'warning': '#fbbf24',
                    'warning-content': '#0b1622',
                    'error': '#f87171',
                    'error-content': '#0b1622',
                    '--rounded-box': '0.5rem',
                    '--rounded-btn': '0.375rem',
                    '--rounded-badge': '0.375rem',
                    '--animation-btn': '0.15s',
                    '--border-btn': '1px',
                },
            },
        ],
        darkTheme: 'lightcmsdark',
        base: true,
        styled: true,
        utils: true,
        logs: false,
    },
};
