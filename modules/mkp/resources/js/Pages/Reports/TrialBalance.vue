<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ asOfDate: String, report: Object })
const asOfDate = ref(props.asOfDate)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function applyFilter() {
  router.get(route('reports.trial-balance'), { as_of_date: asOfDate.value }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Neraca Saldo (Trial Balance)</template>
    <template #actions>
      <input v-model="asOfDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <a :href="route('reports.trial-balance.pdf', { as_of_date: asOfDate })" target="_blank" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">⬇ PDF</a>
      <a :href="route('reports.trial-balance.excel', { as_of_date: asOfDate })" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Unduh Excel</a>
    </template>

    <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500 dark:text-slate-400">Per tanggal {{ asOfDate }}</p>
        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="report.is_balanced ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'">
          {{ report.is_balanced ? 'Seimbang' : 'Tidak Seimbang' }}
        </span>
      </div>
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="py-2">Kode</th><th class="py-2">Nama Akun</th><th class="py-2 text-right">Debit</th><th class="py-2 text-right">Kredit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in report.rows" :key="r.code" class="border-b border-slate-50 dark:border-slate-800">
            <td class="py-2 font-mono text-slate-500 dark:text-slate-400">{{ r.code }}</td>
            <td class="py-2 text-slate-700 dark:text-slate-200">{{ r.name }}</td>
            <td class="py-2 text-right">{{ r.debit > 0 ? formatIDR(r.debit) : '-' }}</td>
            <td class="py-2 text-right">{{ r.credit > 0 ? formatIDR(r.credit) : '-' }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t-2 border-slate-100 dark:border-slate-700 font-semibold">
            <td colspan="2" class="py-3 text-right text-slate-500 dark:text-slate-400">Total</td>
            <td class="py-3 text-right">{{ formatIDR(report.total_debit) }}</td>
            <td class="py-3 text-right">{{ formatIDR(report.total_credit) }}</td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>
  </AppLayout>
</template>
