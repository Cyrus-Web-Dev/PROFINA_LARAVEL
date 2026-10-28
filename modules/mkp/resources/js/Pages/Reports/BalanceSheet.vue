<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ asOfDate: String, report: Object })
const asOfDate = ref(props.asOfDate)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function applyFilter() {
  router.get(route('reports.balance-sheet'), { as_of_date: asOfDate.value }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Neraca (Laporan Posisi Keuangan)</template>
    <template #actions>
      <input v-model="asOfDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <span class="rounded-full px-3 py-1 text-xs font-medium" :class="report.is_balanced ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'">
        {{ report.is_balanced ? 'Balance' : 'Tidak Balance' }}
      </span>
      <a :href="route('reports.balance-sheet.pdf', { as_of_date: asOfDate })" target="_blank" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">⬇ PDF</a>
      <a :href="route('reports.balance-sheet.excel', { as_of_date: asOfDate })" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Unduh Excel</a>
    </template>

    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Per tanggal {{ asOfDate }}</p>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">ASET</h4>
        <div v-for="a in report.assets" :key="a.code" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
          <span class="text-slate-600 dark:text-slate-300">{{ a.name }}</span><span>{{ formatIDR(a.amount) }}</span>
        </div>
        <div class="mt-2 flex justify-between border-t-2 border-slate-100 dark:border-slate-700 pt-2 text-sm font-bold">
          <span>Total Aset</span><span>{{ formatIDR(report.total_assets) }}</span>
        </div>
      </div>

      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">KEWAJIBAN</h4>
        <div v-for="l in report.liabilities" :key="l.code" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
          <span class="text-slate-600 dark:text-slate-300">{{ l.name }}</span><span>{{ formatIDR(l.amount) }}</span>
        </div>
        <div class="mt-2 flex justify-between border-t border-slate-100 dark:border-slate-700 pt-2 text-sm font-semibold">
          <span>Total Kewajiban</span><span>{{ formatIDR(report.total_liabilities) }}</span>
        </div>

        <h4 class="mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">EKUITAS</h4>
        <div v-for="e in report.equity" :key="e.code" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
          <span class="text-slate-600 dark:text-slate-300">{{ e.name }}</span><span>{{ formatIDR(e.amount) }}</span>
        </div>
        <div class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
          <span class="text-slate-600 dark:text-slate-300">Laba Tahun Berjalan</span><span>{{ formatIDR(report.current_year_earnings) }}</span>
        </div>
        <div class="mt-2 flex justify-between border-t-2 border-slate-100 dark:border-slate-700 pt-2 text-sm font-bold">
          <span>Total Kewajiban + Ekuitas</span><span>{{ formatIDR(report.total_liabilities + report.total_equity) }}</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
