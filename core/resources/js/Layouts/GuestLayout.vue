<script setup>
</script>

<template>
  <div class="auth-bg relative flex min-h-screen items-center justify-center overflow-hidden p-4">
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
      <div class="auth-blob auth-blob-1 h-96 w-96 -left-20 -top-20"></div>
      <div class="auth-blob auth-blob-2 h-96 w-96 top-1/3 -right-24"></div>
      <div class="auth-blob auth-blob-3 h-80 w-80 bottom-0 left-1/4"></div>

      <svg class="absolute bottom-0 left-0 h-56 w-full opacity-50" viewBox="0 0 1440 240" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="authLineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#dbb968"/>
            <stop offset="50%" stop-color="#98aec3"/>
            <stop offset="100%" stop-color="#c99a3b"/>
          </linearGradient>
        </defs>
        <path d="M -100 180 C 250 100, 450 220, 720 140 S 1250 60, 1540 160" fill="none" stroke="url(#authLineGrad)" stroke-width="2.5" stroke-dasharray="10 14" class="auth-drift" />
        <path d="M -100 200 C 280 260, 500 140, 760 190 S 1280 240, 1540 120" fill="none" stroke="url(#authLineGrad)" stroke-width="1.5" opacity=".5" stroke-dasharray="6 10" class="auth-drift-reverse" />
      </svg>
    </div>

    <div class="relative z-10 w-full max-w-md">
      <div class="mb-6 text-center">
        <span class="mb-5 inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur-sm">
          <svg class="h-3.5 w-3.5 text-seal-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
          Login Aman &middot; Verifikasi 2 Langkah
        </span>

        <div class="auth-logo-ring mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-seal-500 text-ink-950 font-serif text-2xl font-bold">S</div>

        <h1 class="mt-4 text-xl font-serif font-bold text-white">PROFINA</h1>
        <p class="text-sm text-ink-400">Sistem Informasi Manajemen Sumber Daya Manusia</p>
      </div>

      <div class="auth-card rounded-2xl bg-white p-8 shadow-2xl dark:bg-ink-900">
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
  0%, 100% { box-shadow: 0 0 0 0 rgba(176, 127, 39, 0.35), 0 0 30px 4px rgba(152, 174, 195, 0.25); }
  50% { box-shadow: 0 0 0 10px rgba(176, 127, 39, 0), 0 0 45px 10px rgba(219, 185, 104, 0.35); }
}

.auth-bg { background: radial-gradient(ellipse at top, #1f3040 0%, #0b141d 65%); }
.auth-blob { position: absolute; border-radius: 9999px; filter: blur(60px); pointer-events: none; }
.auth-blob-1 { background: radial-gradient(circle, rgba(176,127,39,.4), transparent 70%); animation: authFloat1 14s ease-in-out infinite; }
.auth-blob-2 { background: radial-gradient(circle, rgba(107,138,165,.35), transparent 70%); animation: authFloat2 16s ease-in-out infinite; }
.auth-blob-3 { background: radial-gradient(circle, rgba(219,185,104,.3), transparent 70%); animation: authFloat3 12s ease-in-out infinite; }

.auth-drift { animation: authDrift 22s linear infinite; }
.auth-drift-reverse { animation: authDriftReverse 30s linear infinite; }

.auth-card { animation: authCardIn .7s cubic-bezier(.22,1,.36,1) both; }
.auth-logo-ring { animation: authLogoGlow 3.5s ease-in-out infinite; }

@media (prefers-reduced-motion: reduce) {
  .auth-blob-1, .auth-blob-2, .auth-blob-3, .auth-card, .auth-logo-ring, .auth-drift, .auth-drift-reverse { animation: none !important; }
}
</style>
