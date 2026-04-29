import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    safelist: [
        // Work timer widget colors (emerald)
        'bg-emerald-500', 'hover:bg-emerald-600',
        'bg-emerald-50', 'dark:bg-emerald-900/20',
        'border-emerald-100', 'dark:border-emerald-900/40',
        'bg-emerald-400', 'bg-emerald-300',
        'text-emerald-700', 'dark:text-emerald-300',
        'text-emerald-600', 'dark:text-emerald-400',
        'text-emerald-400',
        // Work timer in-progress time color
        'text-yellow-500', 'dark:text-yellow-400',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            maxWidth: {
                '7xl': '1920px', // Re-purpose 7xl for 1920px since it's used heavily in inertia stubs
            },
            screens: {
                '3xl': '1920px',
            }
        },
        container: {
            center: true,
            padding: {
                DEFAULT: '1rem',
                sm: '2rem',
                lg: '4rem',
                xl: '5rem',
                '2xl': '6rem',
            },
            // The container will max out at these screen sizes: default Tailwind + 3xl (1920px)
        }
    },

    plugins: [forms],
};
