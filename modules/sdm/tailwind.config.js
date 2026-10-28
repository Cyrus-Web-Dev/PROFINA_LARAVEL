import forms from '@tailwindcss/forms'

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js}',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Serif berkarakter untuk judul/heading - kesan "buku catatan kepegawaian resmi", bukan template SaaS generik.
                serif: ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
                // Sans netral & sangat mudah dibaca untuk isi/UI.
                sans: ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
                // Monospace untuk data tabular (NIP, nominal gaji, tanggal) - angka jadi rapi & rata.
                mono: ['"IBM Plex Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            colors: {
                // "Ink" - biru dongker tua, dipakai untuk teks utama, sidebar, dan elemen struktural.
                ink: {
                    50: '#f1f4f7', 100: '#dfe6ec', 200: '#c1cedb', 300: '#98aec3',
                    400: '#6b8aa5', 500: '#4c6d8a', 600: '#385571', 700: '#2c435c',
                    800: '#1f3040', 900: '#141F2B', 950: '#0b141d',
                },
                // "Seal" - aksen emas/kuning tua, dipakai TERBATAS untuk penekanan (mis. status penting, tombol utama).
                seal: {
                    50: '#fbf6e9', 100: '#f5ead0', 200: '#ead4a1', 300: '#dbb968',
                    400: '#c99a3b', 500: '#b07f27', 600: '#8f651e', 700: '#714f19',
                    800: '#5c4116', 900: '#4c3714',
                },
                paper: '#f6f4ef',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(20 31 43 / 0.05), 0 1px 3px 0 rgb(20 31 43 / 0.08)',
            },
        },
    },
    plugins: [forms],
}
