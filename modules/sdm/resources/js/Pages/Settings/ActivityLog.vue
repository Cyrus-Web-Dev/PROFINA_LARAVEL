<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ logs: Object, filters: Object, users: Array })

function applyFilters(overrides = {}) {
  router.get(route('activity-log.index'), {
    action: overrides.action ?? props.filters.action,
    user_id: overrides.user_id ?? props.filters.user_id,
    date: overrides.date ?? props.filters.date,
  }, { preserveState: true })
}

const actionColor = (action) => {
  if (action.includes('deleted') || action.includes('rejected') || action.includes('failed')) return 'badge-red'
  if (action.includes('created') || action.includes('approved')) return 'badge-green'
  return 'badge-slate'
}
</script>

<template>
  <AppLayout>
    <Head title="Log Aktivitas" />
    <template #title>Log Aktivitas (Audit Trail)</template>

    <div class="card mb-4">
      <div class="card-body flex flex-wrap items-end gap-3">
        <div>
          <label class="label">Cari Aksi</label>
          <input :value="filters.action" @change="applyFilters({ action: $event.target.value })" placeholder="mis. employee, payroll, login…" class="input max-w-xs" />
        </div>
        <div>
          <label class="label">Pengguna</label>
          <select :value="filters.user_id" @change="applyFilters({ user_id: $event.target.value })" class="input max-w-xs">
            <option value="">Semua</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="label">Tanggal</label>
          <input type="date" :value="filters.date" @change="applyFilters({ date: $event.target.value })" class="input" />
        </div>
      </div>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead>
          <tbody>
            <tr v-for="log in logs.data" :key="log.id">
              <td class="text-ink-400 text-xs font-mono whitespace-nowrap">{{ log.created_at }}</td>
              <td class="font-medium">{{ log.user?.name || 'Sistem' }}</td>
              <td><span :class="actionColor(log.action)" class="font-mono">{{ log.action }}</span></td>
              <td class="text-ink-600 dark:text-ink-300 max-w-md">{{ log.description }}</td>
              <td class="text-ink-400 text-xs font-mono">{{ log.ip_address || '-' }}</td>
            </tr>
            <tr v-if="!logs.data.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Tidak ada aktivitas yang cocok.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="logs.links" /></div>
    </div>
  </AppLayout>
</template>
