import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // AKTIFKAN INI: Memungkinkan kita berpindah mode dengan menambah class 'dark' di tag <html>
    darkMode: 'class', 

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Menghubungkan variabel CSS ke class Tailwind agar dinamis
                primary: {
                    DEFAULT: 'rgb(var(--color-primary) / <alpha-value>)',
                    hover: 'rgb(var(--color-primary-hover) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'rgb(var(--color-accent) / <alpha-value>)',
                    hover: 'rgb(var(--color-accent-hover) / <alpha-value>)',
                },
                theme: {
                    body: 'rgb(var(--color-bg) / <alpha-value>)',
                    surface: 'rgb(var(--color-surface) / <alpha-value>)',
                    border: 'rgb(var(--color-border) / <alpha-value>)',
                    text: 'rgb(var(--color-text-main) / <alpha-value>)',
                    muted: 'rgb(var(--color-text-muted) / <alpha-value>)',
                }
            },
        },
    },
    plugins: [forms],
};