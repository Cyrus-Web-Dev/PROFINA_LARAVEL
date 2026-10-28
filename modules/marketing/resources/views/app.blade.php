<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'PROFINA') }}</title>
        <link rel="icon" type="image/svg+xml" href="/images/logo.svg">

        {{-- Terapkan tema gelap SEBELUM halaman tergambar (mencegah kilatan putih & teks tak terbaca) --}}
        <script>
            try { if (localStorage.getItem('profina-theme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) {}
        </script>

        {{-- Font: Fraunces (judul/heading) + IBM Plex Sans (isi) + IBM Plex Mono (data tabular: NIP, nominal, tanggal) --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Indikator muat awal; dihapus otomatis oleh app.js begitu aplikasi siap --}}
        <div id="boot-loader" style="position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;font-family:system-ui,sans-serif;color:#4c6d8a;text-align:center;padding:24px;background:#f6f4ef">
            <div style="width:32px;height:32px;border:3px solid #c1cedb;border-top-color:#b07f27;border-radius:50%;animation:sdmspin .8s linear infinite"></div>
            <div>Memuat PROFINA Marketing&hellip;</div>
            <div id="boot-slow" style="display:none;max-width:420px;font-size:13px;line-height:1.5">
                Lebih lama dari biasanya. Jika kamu memakai <code>npm run dev</code>, pastikan jendela terminalnya masih berjalan
                (atau jalankan <code>npm run build</code> lalu hapus file <code>public/hot</code>). Tekan F12 &rarr; Console untuk melihat error.
            </div>
        </div>
        <style>@keyframes sdmspin{to{transform:rotate(360deg)}}</style>
        <script>setTimeout(function(){var e=document.getElementById('boot-slow');if(e)e.style.display='block'},12000)</script>
        <noscript>Aplikasi ini membutuhkan JavaScript.</noscript>
    </body>
</html>
