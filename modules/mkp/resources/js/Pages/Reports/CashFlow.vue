<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ startDate: String, endDate: String, report: Object })
const startDate = ref(props.startDate)
const endDate = ref(props.endDate)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function applyFilter() {
  router.get(route('reports.cash-flow'), { start_date: startDate.value, end_date: endDate.value }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Laporan Arus Kas</template>
    <template #actions>
      <input v-model="startDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <input v-model="endDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
    </template>

    <div class="mx-auto max-w-2xl rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <p class="text-center text-sm text-slate-500 dark:text-slate-400">Periode {{ startDate }} s/d {{ endDate }} — Metode Tidak Langsung</p>

      <h4 class="mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Arus Kas dari Aktivitas Operasi</h4>
      <div class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm"><span class="text-slate-600 dark:text-slate-300">Laba Bersih</span><span>{{ formatIDR(report.net_income) }}</span></div>
      <div class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm"><span class="text-slate-600 dark:text-slate-300">Depresiasi (beban non-kas)</span><span>{{ formatIDR(report.depreciation) }}</span></div>
      <div v-for="w in report.working_capital_items" :key="w.name" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
        <span class="text-slate-500 dark:text-slate-400">Perubahan {{ w.name }}</span><span :class="w.impact >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ formatIDR(w.impact) }}</span>
      </div>
      <div class="flex justify-between border-b border-slate-200 dark:border-slate-700 py-2 text-sm font-semibold"><span>Kas Bersih dari Operasi</span><span>{{ formatIDR(report.operating_cash_flow) }}</span></div>

      <h4 class="mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Arus Kas dari Aktivitas Investasi</h4>
      <div v-for="i in report.investing_items" :key="i.name" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
        <span class="text-slate-500 dark:text-slate-400">{{ i.name }}</span><span :class="i.impact >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ formatIDR(i.impact) }}</span>
      </div>
      <div v-if="report.investing_items.length === 0" class="py-1.5 text-sm text-slate-400 dark:text-slate-500">Tidak ada aktivitas investasi pada periode ini.</div>
      <div class="flex justify-between border-b border-slate-200 dark:border-slate-700 py-2 text-sm font-semibold"><span>Kas Bersih dari Investasi</span><span>{{ formatIDR(report.investing_cash_flow) }}</span></div>

      <h4 class="mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">Arus Kas dari Aktivitas Pendanaan</h4>
      <div v-for="f in report.financing_items" :key="f.name" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-1.5 text-sm">
        <span class="text-slate-500 dark:text-slate-400">{{ f.name }}</span><span :class="f.impact >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ formatIDR(f.impact) }}</span>
      </div>
      <div v-if="report.financing_items.length === 0" class="py-1.5 text-sm text-slate-400 dark:text-slate-500">Tidak ada aktivitas pendanaan pada periode ini.</div>
      <div class="flex justify-between border-b border-slate-200 dark:border-slate-700 py-2 text-sm font-semibold"><span>Kas Bersih dari Pendanaan</span><span>{{ formatIDR(report.financing_cash_flow) }}</span></div>

      <div class="mt-4 flex justify-between rounded-lg px-4 py-3 text-base font-bold"
        :class="report.net_cash_change >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600'">
        <span>Kenaikan (Penurunan) Kas Bersih</span><span>{{ formatIDR(report.net_cash_change) }}</span>
      </div>

      <p class="mt-3 text-center text-xs" :class="report.is_reconciled ? 'text-emerald-600' : 'text-amber-600'">
        {{ report.is_reconciled ? '✓ Selaras dengan perubahan saldo Kas & Bank aktual' : `⚠ Selisih rekonsiliasi: ${formatIDR(Math.abs(report.net_cash_change - report.actual_cash_change))} (periksa klasifikasi akun)` }}
      </p>
    </div>
  </AppLayout>
</template>
