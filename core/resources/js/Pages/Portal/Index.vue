<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

defineProps({ doors: Array, ssoError: String })
</script>

<template>
  <AppLayout>
    <Head title="Pintu Tugas" />
    <template #title>Pintu Tugas</template>

    <div v-if="ssoError" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">{{ ssoError }}</div>

    <div v-if="!doors.length" class="card card-body max-w-xl">
      <h2 class="font-serif text-lg font-semibold">Belum ada penugasan</h2>
      <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">
        Akunmu sudah aktif, tetapi belum ditugaskan ke modul mana pun. Minta Admin PROFINA untuk memberi penugasan
        (misalnya Marketing, Keuangan, atau SDM), lalu muat ulang halaman ini.
      </p>
    </div>

    <template v-else>
      <p class="mb-6 max-w-2xl text-sm text-ink-600 dark:text-ink-300">
        Kamu hanya bisa masuk ke pintu yang ditugaskan kepadamu. Setiap masuk, Portal membuat tiket sekali pakai
        yang berlaku untuk satu modul saja.
      </p>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <component
          v-for="d in doors" :key="d.code"
          :is="d.code === 'core' ? Link : 'a'"
          :href="d.code === 'core' ? route('dashboard') : route('portal.launch', d.code)"
          class="card group block overflow-hidden transition hover:-translate-y-0.5 hover:shadow-lg"
        >
          <div class="h-1.5" :style="{ background: d.color }"></div>
          <div class="card-body">
            <div class="flex items-start justify-between gap-3">
              <h2 class="font-serif text-lg font-semibold text-ink-900 dark:text-ink-100">{{ d.name }}</h2>
              <span class="badge-slate shrink-0">{{ d.role }}</span>
            </div>
            <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">{{ d.description }}</p>
            <p class="mt-4 text-sm font-medium text-seal-700 group-hover:underline dark:text-seal-400">Masuk &rarr;</p>
          </div>
        </component>
      </div>
    </template>
  </AppLayout>
</template>
