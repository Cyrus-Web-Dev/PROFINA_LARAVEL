import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: ['resources/views/**'],
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        // Pakai 127.0.0.1 secara eksplisit: "localhost" di Node 17+ bisa jadi IPv6 (::1),
        // sementara `php artisan serve` berjalan di IPv4 -> browser menunggu koneksi sampai timeout.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: { host: '127.0.0.1' },
        watch: {
            // Tanpa ini Vite memantau ribuan file vendor/ & storage/ (sangat lambat di Windows).
            ignored: ['**/vendor/**', '**/storage/**', '**/bootstrap/cache/**', '**/public/build/**', '**/node_modules/**'],
        },
    },
})
