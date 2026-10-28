<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'MKP') }}</title>
        <link rel="icon" type="image/svg+xml" href="/images/logo.svg">

        {{-- Terapkan tema gelap SEBELUM halaman tergambar (mencegah kilatan putih & teks tak terbaca) --}}
        <script>
            try { if (localStorage.getItem('mkp-theme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) {}
        </script>

        {{-- Tidak ada font/CSS dari internet di sini: semua dari server sendiri, jadi tidak ada yang bisa membuat halaman menunggu. --}}
        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Indikator muat awal; dihapus otomatis oleh app.js begitu aplikasi siap --}}
        <div id="boot-loader" style="position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;font-family:system-ui,sans-serif;color:#64748b;text-align:center;padding:24px">
            <div style="width:32px;height:32px;border:3px solid #cbd5e1;border-top-color:#059669;border-radius:50%;animation:mkpspin .8s linear infinite"></div>
            <div>Memuat MKP&hellip;</div>
            <div id="boot-slow" style="display:none;max-width:420px;font-size:13px;line-height:1.5">
                Lebih lama dari biasanya. Jika kamu memakai <code>npm run dev</code>, pastikan jendela terminalnya masih berjalan
                (atau jalankan <code>npm run build</code> lalu hapus file <code>public/hot</code>). Tekan F12 &rarr; Console untuk melihat error.
            </div>
        </div>
        <style>@keyframes mkpspin{to{transform:rotate(360deg)}}</style>
        <script>setTimeout(function(){var e=document.getElementById('boot-slow');if(e)e.style.display='block'},12000)</script>
        <noscript>Aplikasi ini membutuhkan JavaScript.</noscript>
    </body>
</html>
