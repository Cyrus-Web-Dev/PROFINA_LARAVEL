import '../css/app.css'
import './bootstrap'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'

const appName = import.meta.env.VITE_APP_NAME || 'MKP'
const APP_ID = 'app'

/**
 * Ambil data halaman awal dari HTML yang dirender server.
 * Mendukung DUA format supaya tidak error "\"undefined\" is not valid JSON" bila versi paket
 * PHP (inertiajs/inertia-laravel) dan JS (@inertiajs/vue3) tidak sama generasinya:
 *   - lama : <div id="app" data-page='{...}'>
 *   - baru : <script data-page="app" type="application/json">{...}</script>
 */
function readInitialPage() {
    const el = document.getElementById(APP_ID)
    if (el && el.dataset.page) {
        return JSON.parse(el.dataset.page)
    }

    const script = document.querySelector(`script[data-page="${APP_ID}"][type="application/json"]`)
    if (script && script.textContent) {
        return JSON.parse(script.textContent)
    }

    return null
}

/** Tampilkan panel error yang bisa dipahami, bukan layar putih kosong. */
function showBootError(title, details) {
    document.getElementById('boot-loader')?.remove()

    const box = document.createElement('div')
    box.style.cssText = 'max-width:640px;margin:48px auto;padding:24px;border:1px solid #fecdd3;background:#fff1f2;border-radius:12px;font-family:system-ui,sans-serif;color:#881337;line-height:1.55'

    const h1 = document.createElement('h1')
    h1.style.cssText = 'font-size:18px;font-weight:700;margin:0 0 8px'
    h1.textContent = title
    box.appendChild(h1)

    const ul = document.createElement('ul')
    ul.style.cssText = 'margin:0;padding-left:20px;font-size:14px'
    details.forEach((line) => {
        const li = document.createElement('li')
        li.textContent = line
        ul.appendChild(li)
    })
    box.appendChild(ul)

    document.body.appendChild(box)
}

let initialPage = null
try {
    initialPage = readInitialPage()
} catch (e) {
    console.error('[MKP] Gagal membaca data halaman:', e)
}

if (!initialPage) {
    showBootError('Halaman tidak bisa dimuat (data Inertia tidak ditemukan)', [
        'Penyebab paling umum: versi paket tidak sejajar. Jalankan: composer show inertiajs/inertia-laravel  dan  npm ls @inertiajs/vue3  — keduanya harus 1.x.',
        'Perbaiki dengan: composer install  &&  npm install  (jangan jalankan "composer require"/"npm install <paket>" tanpa versi).',
        'Lalu: php artisan optimize:clear  dan muat ulang halaman (Ctrl+F5).',
        'Detail error lain ada di storage/logs/laravel.log.',
    ])
} else if (typeof window.route !== 'function') {
    showBootError('Ziggy (fungsi route()) tidak termuat', [
        'Pastikan paket PHP terpasang: composer require tightenco/ziggy:^2.0  (atau composer install).',
        'Pastikan resources/views/app.blade.php memuat @routes sebelum @vite.',
        'Lalu: php artisan optimize:clear dan muat ulang (Ctrl+F5).',
    ])
} else {
    createInertiaApp({
        page: initialPage,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
        setup({ el, App, props, plugin }) {
            const app = createApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue)
                .mount(el)

            document.getElementById('boot-loader')?.remove()

            return app
        },
        progress: { color: '#059669' },
    }).catch((error) => {
        console.error('[MKP] Gagal memulai aplikasi:', error)
        showBootError('Aplikasi gagal dimulai', [
            String(error && error.message ? error.message : error),
            'Tekan F12 -> Console untuk detail. Jika pesannya "Page not found", ada file halaman Vue yang hilang di resources/js/Pages.',
        ])
    })
}
