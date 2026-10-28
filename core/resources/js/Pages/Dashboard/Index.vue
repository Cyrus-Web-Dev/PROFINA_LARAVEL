<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({ modules: Array, canMonitor: Boolean, stats: Object, perModule: Object, sessions: Array, recent: Array })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const refresh = () => router.get(route('dashboard'), { fresh: 1 }, { preserveScroll: true, only: ['modules', 'sessions', 'stats'] })
const revoke = (s) => { if (confirm(`Cabut sesi ${s.user} di modul ${s.module}?`)) router.post(route('sessions.revoke', s.id), {}, { preserveScroll: true }) }
const down = computed(() => props.modules.filter((m) => !m.up).length)
</script>

<template>
  <AppLayout>
    <Head title="Dashboard Inti" />
    <template #title>Dashboard Inti — Pemantau Modul</template>
    <template #actions><button v-if="canMonitor" class="btn-secondary btn-sm" @click="refresh">Muat ulang status</button></template>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="card card-body"><p class="label">Pengguna aktif</p><p class="font-mono text-2xl">{{ stats.users_active }}</p></div>
      <div class="card card-body"><p class="label">Menunggu persetujuan</p><p class="font-mono text-2xl">{{ stats.users_pending }}</p></div>
      <div class="card card-body"><p class="label">Total penugasan modul</p><p class="font-mono text-2xl">{{ stats.assignments }}</p></div>
      <div class="card card-body"><p class="label">Sesi modul aktif (10 mnt)</p><p class="font-mono text-2xl">{{ stats.sessions_active }}</p></div>
    </div>

    <template v-if="canMonitor">
      <div class="mt-8 flex items-center gap-3">
        <h2 class="font-serif text-base font-semibold text-ink-900 dark:text-ink-100">Kesehatan modul</h2>
        <span v-if="down" class="badge-red">{{ down }} bermasalah</span><span v-else class="badge-green">semua normal</span>
      </div>
      <div class="mt-3 grid gap-4 lg:grid-cols-2">
        <div v-for="m in modules" :key="m.code" class="card overflow-hidden">
          <div class="h-1" :style="{ background: m.color }"></div>
          <div class="card-body">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="font-semibold text-ink-900 dark:text-ink-100">{{ m.name }}</p>
                <a :href="m.url" target="_blank" rel="noopener" class="text-xs text-ink-500 hover:underline">{{ m.url }}</a>
              </div>
              <span :class="m.up ? 'badge-green' : 'badge-red'">{{ m.up ? 'UP' : 'DOWN' }}</span>
            </div>
            <p v-if="m.error" class="mt-3 text-sm text-red-700 dark:text-red-400">{{ m.error }}</p>
            <dl v-else class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
              <dt class="text-ink-500">Respons</dt><dd class="font-mono">{{ m.latency_ms }} ms</dd>
              <dt class="text-ink-500">Antrean (gagal)</dt><dd class="font-mono">{{ m.queue_pending ?? '-' }} ({{ m.queue_failed ?? '-' }})</dd>
              <dt class="text-ink-500">Pengguna bertugas</dt><dd class="font-mono">{{ perModule[m.code] ?? 0 }}</dd>
              <template v-for="(v, k) in m.metrics" :key="k"><dt class="text-ink-500">{{ String(k).replaceAll('_', ' ') }}</dt><dd class="font-mono">{{ v }}</dd></template>
            </dl>
          </div>
        </div>
      </div>

      <div class="card mt-8">
        <div class="card-header"><h2 class="font-serif text-base font-semibold">Sesi modul yang sedang aktif</h2></div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Pengguna</th><th>Modul</th><th>IP</th><th>Terakhir aktif</th><th></th></tr></thead>
            <tbody>
              <tr v-for="s in sessions" :key="s.id">
                <td>{{ s.user }}<br><span class="text-xs text-ink-500">{{ s.email }}</span></td><td>{{ s.module }}</td><td class="font-mono">{{ s.ip }}</td><td>{{ s.last_seen }}</td>
                <td class="text-right"><button v-if="can('sessions.revoke')" class="btn-ghost btn-sm text-red-600" @click="revoke(s)">Cabut</button></td>
              </tr>
              <tr v-if="!sessions.length"><td colspan="5" class="py-6 text-center text-ink-500">Tidak ada sesi aktif.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <div v-if="recent.length" class="card mt-8">
      <div class="card-header"><h2 class="font-serif text-base font-semibold">Aktivitas terbaru</h2></div>
      <ul class="divide-y divide-ink-100 text-sm dark:divide-ink-800">
        <li v-for="(r, i) in recent" :key="i" class="flex gap-3 px-5 py-2.5"><span class="w-24 shrink-0 font-mono text-ink-500">{{ r.at }}</span><span class="w-28 shrink-0 text-ink-500">{{ r.action }}</span><span>{{ r.description }}</span></li>
      </ul>
    </div>
  </AppLayout>
</template>
