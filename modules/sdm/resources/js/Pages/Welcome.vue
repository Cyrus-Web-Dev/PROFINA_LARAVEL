<script setup>
import { Head, Link } from '@inertiajs/vue3'
</script>

<template>
  <Head title="SDM HRIS" />
  <div class="min-h-screen bg-paper dark:bg-ink-950 flex flex-col">
    <header class="border-b border-ink-200 dark:border-ink-800">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
        <div class="flex items-center gap-2.5">
          <div class="flex h-9 w-9 items-center justify-center rounded-md bg-seal-500 text-ink-950 font-serif font-bold">S</div>
          <span class="font-serif font-semibold text-ink-900 dark:text-ink-100">SDM HRIS</span>
        </div>
        <nav class="flex items-center gap-4 text-sm">
          <Link :href="route('careers.index')" class="text-ink-600 hover:text-ink-900 dark:text-ink-300 dark:hover:text-white">Lowongan Kerja</Link>
          <Link :href="route('login')" class="btn-accent btn-sm">Masuk</Link>
        </nav>
      </div>
    </header>

    <main class="flex flex-1 items-center">
      <div class="mx-auto max-w-3xl px-6 py-20 text-center">
        <h1 class="font-serif text-4xl font-bold text-ink-900 dark:text-ink-50 sm:text-5xl">Sistem Informasi Manajemen SDM</h1>
        <p class="mt-4 text-lg text-ink-500 dark:text-ink-400">Kelola data karyawan, absensi, cuti, payroll, kinerja, hingga rekrutmen dalam satu tempat.</p>
        <div class="mt-8 flex justify-center gap-3">
          <Link :href="route('login')" class="btn-accent">Masuk ke Aplikasi</Link>
          <Link :href="route('careers.index')" class="btn-secondary">Lihat Lowongan Kerja</Link>
        </div>
      </div>
    </main>

    <footer class="border-t border-ink-200 py-6 text-center text-xs text-ink-400 dark:border-ink-800">
      &copy; {{ new Date().getFullYear() }} SDM HRIS
    </footer>
  </div>
</template>
