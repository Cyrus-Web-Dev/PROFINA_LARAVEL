<script setup>
</script>

<template>
  <div class="auth-bg relative flex min-h-screen items-center justify-center overflow-hidden p-4">
    <!-- Wrapper fixed terpisah dari body, supaya background TIDAK pernah mematikan scroll halaman -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
      <div class="auth-blob auth-blob-1 h-96 w-96 -left-20 -top-20"></div>
      <div class="auth-blob auth-blob-2 h-96 w-96 top-1/3 -right-24"></div>
      <div class="auth-blob auth-blob-3 h-80 w-80 bottom-0 left-1/4"></div>

      <svg class="absolute bottom-0 left-0 h-56 w-full opacity-60" viewBox="0 0 1440 240" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="authLineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#34d399"/>
            <stop offset="50%" stop-color="#0ea5e9"/>
            <stop offset="100%" stop-color="#10b981"/>
          </linearGradient>
        </defs>
        <path d="M -100 180 C 250 100, 450 220, 720 140 S 1250 60, 1540 160" fill="none" stroke="url(#authLineGrad)" stroke-width="2.5" stroke-dasharray="10 14" class="auth-drift" />
        <path d="M -100 200 C 280 260, 500 140, 760 190 S 1280 240, 1540 120" fill="none" stroke="url(#authLineGrad)" stroke-width="1.5" opacity=".5" stroke-dasharray="6 10" class="auth-drift-reverse" />
      </svg>
    </div>

    <div class="relative z-10 w-full max-w-md">
      <div class="mb-6 text-center">
        <span class="mb-5 inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur-sm">
          <svg class="auth-sparkle h-3.5 w-3.5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.448a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.367-2.448a1 1 0 00-1.175 0l-3.367 2.448c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L1.98 9.384c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 00.95-.69l1.286-3.957z"/></svg>
          Login Aman &amp; Terenkripsi
        </span>

        <img src="/images/logo.svg" alt="Logo MKP" class="auth-logo-ring mx-auto h-16 w-16 rounded-full" />

        <h1 class="mt-4 text-xl font-bold text-white">MKP</h1>
        <p class="text-sm text-slate-400">Manajemen Keuangan Perusahaan</p>
      </div>

      <div class="auth-card rounded-2xl bg-white p-8 shadow-2xl dark:bg-slate-900">
        <slot />
      </div>
    </div>
  </div>
</template>

<style>
@keyframes authFloat1 { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(30px, -40px) scale(1.08); } }
@keyframes authFloat2 { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-40px, 30px) scale(1.05); } }
@keyframes authFloat3 { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, 20px) scale(1.1); } }
@keyframes authDrift { to { stroke-dashoffset: -1000; } }
@keyframes authDriftReverse { to { stroke-dashoffset: 1000; } }
@keyframes authCardIn { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
@keyframes authLogoGlow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.35), 0 0 30px 4px rgba(14, 165, 233, 0.25); }
  50% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0), 0 0 45px 10px rgba(45, 212, 191, 0.35); }
}
@keyframes authSparkle { 0%, 100% { opacity: .4; transform: scale(0.85) rotate(0deg); } 50% { opacity: 1; transform: scale(1.15) rotate(15deg); } }

.auth-bg { background: radial-gradient(ellipse at top, #0b1f1a 0%, #050a08 65%); }
.auth-blob { position: absolute; border-radius: 9999px; filter: blur(60px); pointer-events: none; }
.auth-blob-1 { background: radial-gradient(circle, rgba(16,185,129,.5), transparent 70%); animation: authFloat1 14s ease-in-out infinite; }
.auth-blob-2 { background: radial-gradient(circle, rgba(14,165,233,.4), transparent 70%); animation: authFloat2 16s ease-in-out infinite; }
.auth-blob-3 { background: radial-gradient(circle, rgba(45,212,191,.45), transparent 70%); animation: authFloat3 12s ease-in-out infinite; }

.auth-drift { animation: authDrift 22s linear infinite; }
.auth-drift-reverse { animation: authDriftReverse 30s linear infinite; }

.auth-card { animation: authCardIn .7s cubic-bezier(.22,1,.36,1) both; }
.auth-logo-ring { animation: authLogoGlow 3.5s ease-in-out infinite; }
.auth-sparkle { animation: authSparkle 3s ease-in-out infinite; }

@media (prefers-reduced-motion: reduce) {
  .auth-blob-1, .auth-blob-2, .auth-blob-3, .auth-card, .auth-logo-ring, .auth-sparkle, .auth-drift, .auth-drift-reverse { animation: none !important; }
}
</style>
