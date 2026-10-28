<script setup>
import { useCan } from '@/permissions'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ assets: Array })
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)
const statusBadge = { active: 'bg-emerald-100 text-emerald-700', fully_depreciated: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300', disposed: 'bg-rose-100 text-rose-600' }
const statusLabel = { active: 'Aktif', fully_depreciated: 'Terdepresiasi Penuh', disposed: 'Dilepas' }
</script>

<template>
  <AppLayout>
    <template #title>Aset Tetap</template>
    <template #actions>
      <Link v-if="can('fixed-asset.manage')" :href="route('fixed-assets.create')" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">+ Catat Aset Baru</Link>
    </template>

    <div class="overflow-x-auto rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3">No. Aset</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Tgl Perolehan</th>
            <th class="px-4 py-3 text-right">Harga Perolehan</th><th class="px-4 py-3 text-right">Nilai Buku</th>
            <th class="px-4 py-3">Umur (bulan)</th><th class="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in assets" :key="a.id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3"><Link :href="route('fixed-assets.show', a.id)" class="font-medium text-slate-800 dark:text-slate-100 hover:text-emerald-600">{{ a.asset_number }}</Link></td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ a.name }}</td>
            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ a.acquisition_date }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ formatIDR(a.acquisition_cost) }}</td>
            <td class="px-4 py-3 text-right font-mono font-medium">{{ formatIDR(a.book_value) }}</td>
            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ a.months_depreciated }} / {{ a.useful_life_months }}</td>
            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge[a.status]">{{ statusLabel[a.status] }}</span></td>
          </tr>
          <tr v-if="assets.length === 0"><td colspan="7" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada aset tetap.</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
