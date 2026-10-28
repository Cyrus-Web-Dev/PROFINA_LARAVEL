<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({ logs: Object, filters: Object, actionOptions: Array })
const search = ref(props.filters?.search ?? '')
const action = ref(props.filters?.action ?? '')

const actionColor = {
  'login.success': 'bg-emerald-100 text-emerald-700',
  'login.failed': 'bg-rose-100 text-rose-600',
  'logout': 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
  'register.success': 'bg-emerald-100 text-emerald-700',
  'journal.posted': 'bg-blue-100 text-blue-700',
  'journal.voided': 'bg-rose-100 text-rose-600',
  'journal.approved': 'bg-emerald-100 text-emerald-700',
  'journal.rejected': 'bg-rose-100 text-rose-600',
  'invoice.created': 'bg-blue-100 text-blue-700',
  'invoice.voided': 'bg-rose-100 text-rose-600',
  'invoice.payment_recorded': 'bg-emerald-100 text-emerald-700',
  'bill.created': 'bg-blue-100 text-blue-700',
  'bill.voided': 'bg-rose-100 text-rose-600',
  'bill.payment_recorded': 'bg-emerald-100 text-emerald-700',
  'gmail.connected': 'bg-emerald-100 text-emerald-700',
  'gmail.disconnected': 'bg-amber-100 text-amber-700',
}

function applyFilter() {
  router.get(route('settings.activity-log.index'), { search: search.value, action: action.value }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Activity Log (Audit Trail)</template>

    <div class="rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 dark:border-slate-700 p-4">
        <input v-model="search" @keyup.enter="applyFilter" type="text" placeholder="Cari deskripsi..." class="w-64 rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <select v-model="action" @change="applyFilter" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm">
          <option value="">Semua aksi</option>
          <option v-for="a in actionOptions" :key="a" :value="a">{{ a }}</option>
        </select>
        <button @click="applyFilter" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Cari</button>
      </div>

      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Pengguna</th><th class="px-4 py-3">Aksi</th>
            <th class="px-4 py-3">Deskripsi</th><th class="px-4 py-3">IP</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs.data" :key="log.id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ log.created_at }}</td>
            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ log.user?.name ?? 'Sistem/Tamu' }}</td>
            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="actionColor[log.action] || 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'">{{ log.action }}</span></td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ log.description }}</td>
            <td class="px-4 py-3 font-mono text-xs text-slate-400 dark:text-slate-500">{{ log.ip_address }}</td>
          </tr>
          <tr v-if="logs.data.length === 0"><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada aktivitas tercatat.</td></tr>
        </tbody>
      </table>
      </div>

      <div v-if="logs.links?.length > 3" class="flex flex-wrap gap-1 border-t border-slate-100 dark:border-slate-700 p-4">
        <Link v-for="(link, i) in logs.links" :key="i" :href="link.url || '#'" v-html="link.label"
          class="rounded-lg px-3 py-1.5 text-xs"
          :class="link.active ? 'bg-slate-800 text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700'" />
      </div>
    </div>
  </AppLayout>
</template>
