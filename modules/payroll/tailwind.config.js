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
                // "Ink" - hijau-teal tua (tinta buku besar): teks utama, sidebar, struktur. Beda dari SDM (biru) & MKP agar tiap modul ERP mudah dikenali.
                ink: {
                    50: '#f0f5f4', 100: '#dce8e6', 200: '#bdd2cf', 300: '#92b3ae',
                    400: '#648d87', 500: '#46706b', 600: '#365957', 700: '#2b4645',
                    800: '#1d3231', 900: '#12302f', 950: '#0a1c1c',
                },
                // "Seal" - hijau buku besar: aksi utama & status sehat/terverifikasi.
                seal: {
                    50: '#eaf6f1', 100: '#cfece1', 200: '#a2d9c4', 300: '#6ec0a3',
                    400: '#3fa383', 500: '#17806a', 600: '#126a58', 700: '#0f5547',
                    800: '#0d443a', 900: '#0b382f',
                },
                // "Stamp" - merah stempel: dipakai HANYA untuk pengecualian kontrol (temuan kritis, rantai rusak, ditolak).
                stamp: {
                    50: '#fdf1ef', 100: '#fadcd7', 200: '#f3b4aa', 300: '#e9877a',
                    400: '#d9594a', 500: '#c2412d', 600: '#a3331f', 700: '#842a1b', 800: '#6c261b', 900: '#5a241c',
                },
                paper: '#f1f4f2',
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(20 31 43 / 0.05), 0 1px 3px 0 rgb(20 31 43 / 0.08)',
            },
        },
    },
    plugins: [forms],
}
