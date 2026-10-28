<script setup>
import { useCan } from '@/permissions'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, Link } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ year: Number, month: Number, comparison: Object })
const year = ref(props.year)
const month = ref(props.month)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)
const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']

function applyFilter() {
  router.get(route('budgets.index'), { year: year.value, month: month.value }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Anggaran vs Realisasi</template>
    <template #actions>
      <select v-model="month" @change="applyFilter" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm">
        <option v-for="(m, i) in monthNames" :key="i" :value="i + 1">{{ m }}</option>
      </select>
      <input v-model="year" @change="applyFilter" type="number" class="w-24 rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <Link v-if="can('budget.manage')" :href="route('budgets.edit', { year, month })" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Edit Anggaran</Link>
    </template>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <p class="text-xs text-slate-400 dark:text-slate-500">Pendapatan: Anggaran vs Realisasi</p>
        <div class="mt-2 flex items-baseline gap-3">
          <p class="text-xl font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(comparison.total_actual_revenue) }}</p>
          <p class="text-sm text-slate-400 dark:text-slate-500">/ {{ formatIDR(comparison.total_budget_revenue) }}</p>
        </div>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <p class="text-xs text-slate-400 dark:text-slate-500">Beban: Anggaran vs Realisasi</p>
        <div class="mt-2 flex items-baseline gap-3">
          <p class="text-xl font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(comparison.total_actual_expense) }}</p>
          <p class="text-sm text-slate-400 dark:text-slate-500">/ {{ formatIDR(comparison.total_budget_expense) }}</p>
        </div>
      </div>
    </div>

    <div class="mt-6 overflow-x-auto rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3">Akun</th><th class="px-4 py-3 text-right">Anggaran</th><th class="px-4 py-3 text-right">Realisasi</th>
            <th class="px-4 py-3 text-right">Selisih</th><th class="px-4 py-3 text-right">%</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in comparison.rows" :key="r.account_id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ r.name }}</td>
            <td class="px-4 py-3 text-right">{{ formatIDR(r.budget) }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ formatIDR(r.actual) }}</td>
            <td class="px-4 py-3 text-right font-medium" :class="r.is_favorable ? 'text-emerald-600' : 'text-rose-600'">{{ formatIDR(r.variance) }}</td>
            <td class="px-4 py-3 text-right text-slate-400 dark:text-slate-500">{{ r.variance_pct !== null ? r.variance_pct + '%' : '-' }}</td>
          </tr>
          <tr v-if="comparison.rows.length === 0"><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada data anggaran/realisasi untuk periode ini.</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
