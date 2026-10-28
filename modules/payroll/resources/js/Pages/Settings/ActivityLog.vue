<script setup>
import { reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ logs: Object, filters: Object, users: Array })
const f = reactive({ action: props.filters.action ?? '', user_id: props.filters.user_id ?? '', date: props.filters.date ?? '' })
const apply = () => router.get(route('activity-log.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true })
</script>

<template>
  <AppLayout>
    <Head title="Log Aktivitas" />
    <template #title>Log Aktivitas</template>
    <p class="mb-3 text-sm text-ink-500">Log aktivitas pengguna (login, pengaturan). Keputusan keuangan yang kritis dicatat terpisah di <a :href="route('audit.index')" class="text-seal-600 underline">Jejak Audit</a> yang tahan-ubah.</p>
    <div class="mb-4 flex flex-wrap gap-2">
      <input v-model="f.action" class="input !w-48" placeholder="Aksi…" @keyup.enter="apply" />
      <select v-model="f.user_id" class="input !w-48" @change="apply"><option value="">Semua pengguna</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option></select>
      <input v-model="f.date" type="date" class="input !w-44" @change="apply" />
      <button class="btn-secondary btn-sm" @click="apply">Terapkan</button>
    </div>
    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead>
        <tbody>
          <tr v-for="l in logs.data" :key="l.id">
            <td class="num whitespace-nowrap text-xs">{{ l.created_at }}</td><td>{{ l.user?.name ?? '—' }}</td>
            <td><span class="badge-slate font-mono">{{ l.action }}</span></td><td>{{ l.description }}</td><td class="num text-xs text-ink-400">{{ l.ip_address }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="logs.links" />
  </AppLayout>
</template>
