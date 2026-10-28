<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler } from 'chart.js'
import { Link } from '@inertiajs/vue3'

ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler)

const props = defineProps({ kpi: Object, trend: Array, draftJournalsCount: Number, pendingApprovalCount: Number, recentJournals: Array, reminders: Object })

const formatIDR = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v ?? 0)

const chartData = {
  labels: props.trend.map(t => t.label),
  datasets: [
    { label: 'Pendapatan', data: props.trend.map(t => t.revenue), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)', tension: 0.35, fill: true },
    { label: 'Beban', data: props.trend.map(t => t.expense), borderColor: '#f43f5e', backgroundColor: 'rgba(244,63,94,0.08)', tension: 0.35, fill: true },
  ],
}
const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { ticks: { callback: (v) => new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(v) } } } }

const cards = [
  { label: 'Total Aset', value: props.kpi.total_assets, color: 'text-slate-900 dark:text-white', accent: 'bg-blue-500' },
  { label: 'Total Kewajiban', value: props.kpi.total_liabilities, color: 'text-slate-900 dark:text-white', accent: 'bg-amber-500' },
  { label: 'Total Ekuitas', value: props.kpi.total_equity, color: 'text-slate-900 dark:text-white', accent: 'bg-violet-500' },
  { label: 'Laba Bersih (YTD)', value: props.kpi.net_income_ytd, color: props.kpi.net_income_ytd >= 0 ? 'text-emerald-600' : 'text-rose-600', accent: props.kpi.net_income_ytd >= 0 ? 'bg-emerald-500' : 'bg-rose-500' },
]

const statusBadge = { draft: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300', posted: 'bg-emerald-100 text-emerald-700', void: 'bg-rose-100 text-rose-600' }
</script>

<template>
  <AppLayout>
    <template #title>Dashboard</template>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="c in cards" :key="c.label" class="relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="absolute right-0 top-0 h-16 w-16 -translate-y-6 translate-x-6 rounded-full opacity-10" :class="c.accent"></div>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ c.label }}</p>
        <p class="mt-2 text-2xl font-semibold" :class="c.color">{{ formatIDR(c.value) }}</p>
      </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700 lg:col-span-2">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tren Pendapatan vs Beban (6 Bulan)</h3>
        <div class="mt-4 h-72">
          <Line :data="chartData" :options="chartOptions" />
        </div>
      </div>

      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jurnal Terbaru</h3>
          <Link :href="route('journals.index')" class="text-xs font-medium text-emerald-600 hover:underline">Lihat semua</Link>
        </div>
        <div v-if="draftJournalsCount > 0" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700">
          {{ draftJournalsCount }} jurnal masih berstatus draft dan menunggu diposting.
        </div>
        <div v-if="pendingApprovalCount > 0" class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-600">
          {{ pendingApprovalCount }} jurnal menunggu approval.
        </div>
        <ul class="mt-4 space-y-3">
          <li v-for="j in recentJournals" :key="j.id" class="flex items-center justify-between">
            <div class="min-w-0">
              <Link :href="route('journals.show', j.id)" class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100 hover:text-emerald-600">{{ j.journal_number }}</Link>
              <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ j.description || 'Tanpa keterangan' }}</p>
            </div>
            <span class="ml-2 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge[j.status]">{{ j.status }}</span>
          </li>
          <li v-if="recentJournals.length === 0" class="text-sm text-slate-400 dark:text-slate-500">Belum ada jurnal.</li>
        </ul>
      </div>
    </div>

    <div v-if="reminders.overdue_invoices.length || reminders.upcoming_invoices.length || reminders.overdue_bills.length || reminders.upcoming_bills.length"
      class="mt-6 rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Pengingat Jatuh Tempo</h3>
      <div class="mt-4 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div>
          <p class="text-xs font-medium text-slate-400 dark:text-slate-500">Piutang (Invoice)</p>
          <ul class="mt-2 space-y-2">
            <li v-for="i in reminders.overdue_invoices" :key="'oi'+i.id" class="flex items-center justify-between text-sm">
              <Link :href="route('invoices.show', i.id)" class="truncate text-slate-700 dark:text-slate-200 hover:text-emerald-600">{{ i.label }}</Link>
              <span class="ml-2 shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-600">Lewat {{ i.due_date }}</span>
            </li>
            <li v-for="i in reminders.upcoming_invoices" :key="'ui'+i.id" class="flex items-center justify-between text-sm">
              <Link :href="route('invoices.show', i.id)" class="truncate text-slate-700 dark:text-slate-200 hover:text-emerald-600">{{ i.label }}</Link>
              <span class="ml-2 shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Jatuh tempo {{ i.due_date }}</span>
            </li>
            <li v-if="!reminders.overdue_invoices.length && !reminders.upcoming_invoices.length" class="text-sm text-slate-400 dark:text-slate-500">Tidak ada piutang jatuh tempo.</li>
          </ul>
        </div>
        <div>
          <p class="text-xs font-medium text-slate-400 dark:text-slate-500">Utang (Bill)</p>
          <ul class="mt-2 space-y-2">
            <li v-for="b in reminders.overdue_bills" :key="'ob'+b.id" class="flex items-center justify-between text-sm">
              <Link :href="route('bills.show', b.id)" class="truncate text-slate-700 dark:text-slate-200 hover:text-emerald-600">{{ b.label }}</Link>
              <span class="ml-2 shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-600">Lewat {{ b.due_date }}</span>
            </li>
            <li v-for="b in reminders.upcoming_bills" :key="'ub'+b.id" class="flex items-center justify-between text-sm">
              <Link :href="route('bills.show', b.id)" class="truncate text-slate-700 dark:text-slate-200 hover:text-emerald-600">{{ b.label }}</Link>
              <span class="ml-2 shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Jatuh tempo {{ b.due_date }}</span>
            </li>
            <li v-if="!reminders.overdue_bills.length && !reminders.upcoming_bills.length" class="text-sm text-slate-400 dark:text-slate-500">Tidak ada utang jatuh tempo.</li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
