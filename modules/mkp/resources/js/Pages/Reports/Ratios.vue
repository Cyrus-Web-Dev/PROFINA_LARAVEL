<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({ asOfDate: String, startDate: String, ratios: Object })
const asOfDate = ref(props.asOfDate)
const startDate = ref(props.startDate)

function applyFilter() {
  router.get(route('reports.ratios'), { as_of_date: asOfDate.value, start_date: startDate.value }, { preserveState: true })
}
function pct(v) {
  return v === null ? '-' : `${(v * 100).toFixed(1)}%`
}
function ratio(v) {
  return v === null ? '-' : `${v.toFixed(2)}x`
}

// computed: kalau tidak, rasio tidak ikut berubah ketika tanggal filter diganti (props baru dari server)
const groups = computed(() => [
  {
    title: 'Likuiditas', desc: 'Kemampuan membayar kewajiban jangka pendek',
    items: [
      { label: 'Current Ratio', value: ratio(props.ratios.liquidity.current_ratio), note: 'Aset Lancar / Kewajiban Lancar. Idealnya > 1x — aset lancar cukup untuk menutup utang jangka pendek.' },
      { label: 'Quick Ratio', value: ratio(props.ratios.liquidity.quick_ratio), note: '(Aset Lancar - Persediaan) / Kewajiban Lancar. Lebih konservatif dari Current Ratio.' },
    ],
  },
  {
    title: 'Solvabilitas', desc: 'Struktur permodalan dan tingkat utang perusahaan',
    items: [
      { label: 'Debt to Equity', value: ratio(props.ratios.solvency.debt_to_equity), note: 'Total Kewajiban / Total Ekuitas. Semakin rendah, semakin kecil ketergantungan pada utang.' },
      { label: 'Debt to Asset', value: ratio(props.ratios.solvency.debt_to_asset), note: 'Total Kewajiban / Total Aset. Menunjukkan porsi aset yang dibiayai utang.' },
    ],
  },
  {
    title: 'Profitabilitas', desc: 'Kemampuan menghasilkan laba dari operasi',
    items: [
      { label: 'Net Profit Margin', value: pct(props.ratios.profitability.net_profit_margin), note: 'Laba Bersih / Pendapatan. Persentase pendapatan yang menjadi laba.' },
      { label: 'Return on Assets (ROA)', value: pct(props.ratios.profitability.return_on_assets), note: 'Laba Bersih / Total Aset. Efisiensi penggunaan aset untuk menghasilkan laba.' },
      { label: 'Return on Equity (ROE)', value: pct(props.ratios.profitability.return_on_equity), note: 'Laba Bersih / Total Ekuitas. Tingkat imbal hasil bagi pemilik modal.' },
    ],
  },
])
</script>

<template>
  <AppLayout>
    <template #title>Rasio Keuangan</template>
    <template #actions>
      <input v-model="startDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <input v-model="asOfDate" @change="applyFilter" type="date" class="rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
    </template>

    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Profitabilitas dihitung untuk periode {{ startDate }} s/d {{ asOfDate }}. Likuiditas & Solvabilitas per tanggal {{ asOfDate }}.</p>

    <div class="space-y-6">
      <div v-for="g in groups" :key="g.title" class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ g.title }}</h3>
        <p class="text-xs text-slate-400 dark:text-slate-500">{{ g.desc }}</p>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div v-for="item in g.items" :key="item.label" class="rounded-xl bg-slate-50 dark:bg-slate-900/40 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ item.label }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ item.value }}</p>
            <p class="mt-2 text-xs leading-relaxed text-slate-400 dark:text-slate-500">{{ item.note }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
