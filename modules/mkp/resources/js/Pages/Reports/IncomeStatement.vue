<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ startDate: String, endDate: String, report: Object })
const startDate = ref(props.startDate)
const endDate = ref(props.endDate)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function applyFilter() {
  router.get(route('reports.income-statement'), { start_date: startDate.value, end_date: endDate.value }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Laporan Laba Rugi</template>
    <template #actions>
      <input v-model="startDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <input v-model="endDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <a :href="route('reports.income-statement.pdf', { start_date: startDate, end_date: endDate })" target="_blank" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">⬇ PDF</a>
      <a :href="route('reports.income-statement.excel', { start_date: startDate, end_date: endDate })" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Unduh Excel</a>
    </template>

    <div class="mx-auto max-w-2xl rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <p class="text-center text-sm text-slate-500 dark:text-slate-400">Periode {{ startDate }} s/d {{ endDate }}</p>

      <h4 class="mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Pendapatan</h4>
      <div v-for="r in report.revenues" :key="r.code" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
        <span class="text-slate-600 dark:text-slate-300">{{ r.name }}</span><span>{{ formatIDR(r.amount) }}</span>
      </div>
      <div class="flex justify-between border-b border-slate-200 dark:border-slate-700 py-2 text-sm font-semibold">
        <span>Total Pendapatan</span><span>{{ formatIDR(report.total_revenue) }}</span>
      </div>

      <h4 class="mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Beban</h4>
      <div v-for="r in report.expenses" :key="r.code" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
        <span class="text-slate-600 dark:text-slate-300">{{ r.name }}</span><span>{{ formatIDR(r.amount) }}</span>
      </div>
      <div class="flex justify-between border-b border-slate-200 dark:border-slate-700 py-2 text-sm font-semibold">
        <span>Total Beban</span><span>{{ formatIDR(report.total_expense) }}</span>
      </div>

      <div class="mt-4 flex justify-between rounded-lg px-4 py-3 text-base font-bold"
        :class="report.net_income >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600'">
        <span>{{ report.net_income >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</span>
        <span>{{ formatIDR(Math.abs(report.net_income)) }}</span>
      </div>
    </div>
  </AppLayout>
</template>
