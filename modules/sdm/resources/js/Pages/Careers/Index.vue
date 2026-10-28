<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({ vacancies: Array })
const typeLabels = { tetap: 'Tetap (PKWTT)', kontrak: 'Kontrak (PKWT)', magang: 'Magang', harian: 'Harian Lepas' }
</script>

<template>
  <Head title="Lowongan Kerja" />
  <div class="min-h-screen bg-paper dark:bg-ink-950">
    <header class="border-b border-ink-200 dark:border-ink-800">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
        <Link :href="route('welcome')" class="flex items-center gap-2.5">
          <div class="flex h-9 w-9 items-center justify-center rounded-md bg-seal-500 text-ink-950 font-serif font-bold">S</div>
          <span class="font-serif font-semibold text-ink-900 dark:text-ink-100">SDM HRIS</span>
        </Link>
        <Link :href="route('login')" class="btn-secondary btn-sm">Masuk</Link>
      </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-12">
      <h1 class="font-serif text-3xl font-bold text-ink-900 dark:text-ink-50">Lowongan Kerja</h1>
      <p class="mt-2 text-ink-500 dark:text-ink-400">Bergabunglah dengan tim kami.</p>

      <div class="mt-8 space-y-4">
        <Link v-for="v in vacancies" :key="v.id" :href="route('careers.show', v.slug)" class="card card-body block hover:border-seal-400">
          <h2 class="font-serif text-lg font-semibold text-ink-900 dark:text-ink-100">{{ v.title }}</h2>
          <p class="mt-1 text-sm text-ink-500">{{ v.department?.name || 'Umum' }} &middot; {{ typeLabels[v.employment_type] }}</p>
        </Link>
        <p v-if="!vacancies.length" class="text-center text-sm text-ink-400 py-10">Belum ada lowongan terbuka saat ini.</p>
      </div>
    </main>
  </div>
</template>
