<script setup>
import { computed, reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { MONTHS } from '@/fmt.js'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({ year: Number, plans: Array, report: Object, sim: Object, simInput: Object, departments: Array })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const sim = reactive({ pct: props.simInput.pct ?? 5, dept: props.simInput.dept ?? '', from_month: props.simInput.from_month ?? new Date().getMonth() + 1 })
const go = (extra = {}) => router.get(route('budgets.index'), { year: props.year, ...extra }, { preserveScroll: true })
const create = () => router.post(route('budgets.store'), { year: props.year, copy_from: props.plans[0]?.id })
const t = computed(() => props.report.totals)
const max = computed(() => Math.max(1, ...t.value.budget_by_month, ...t.value.actual_by_month))
const pctUsed = computed(() => (t.value.budget > 0 ? Math.round((t.value.actual / t.value.budget) * 100) : 0))
</script>

<template>
  <AppLayout>
    <Head title="Anggaran SDM" />
    <template #title>Anggaran SDM {{ year }}</template>
    <template #actions>
      <button class="btn-secondary btn-sm" @click="go({ year: year - 1 })">‹</button><button class="btn-secondary btn-sm" @click="go({ year: year + 1 })">›</button>
      <button v-if="can('budgets.manage')" class="btn-primary btn-sm" @click="create">+ Anggaran / versi baru</button>
    </template>

    <div class="grid gap-6 xl:grid-cols-3">
      <section class="card xl:col-span-2">
        <header class="card-header"><div><h2 class="text-base font-semibold">Anggaran vs realisasi per bulan</h2><p class="text-xs text-ink-500">Realisasi = biaya perusahaan (bruto + BPJS perusahaan) dari putaran yang sudah terkunci.</p></div></header>
        <div class="card-body">
          <div class="flex h-44 items-end gap-2">
            <div v-for="(b, i) in t.budget_by_month" :key="i" class="flex flex-1 flex-col items-center justify-end gap-1" :title="`${MONTHS[i]}: anggaran Rp ${Math.round(b).toLocaleString('id-ID')} · realisasi Rp ${Math.round(t.actual_by_month[i]).toLocaleString('id-ID')}`">
              <div class="flex h-full w-full items-end gap-0.5"><div class="flex-1 rounded-t bg-ink-200 dark:bg-ink-700" :style="{ height: (b / max) * 100 + '%' }"></div>
                <div class="flex-1 rounded-t" :class="t.actual_by_month[i] > b && b > 0 ? 'bg-stamp-500' : 'bg-seal-500'" :style="{ height: (t.actual_by_month[i] / max) * 100 + '%' }"></div></div>
              <span class="text-[10px] text-ink-500">{{ MONTHS[i].slice(0, 3) }}</span></div>
          </div>
          <div class="mt-3 flex flex-wrap gap-4 text-xs text-ink-500"><span><i class="mr-1 inline-block h-2 w-2 bg-ink-300"></i>Anggaran</span><span><i class="mr-1 inline-block h-2 w-2 bg-seal-500"></i>Realisasi</span><span><i class="mr-1 inline-block h-2 w-2 bg-stamp-500"></i>Melebihi anggaran bulan itu</span></div>
        </div>
        <div class="grid gap-3 border-t border-ink-100 p-5 sm:grid-cols-4 dark:border-ink-800">
          <div><p class="text-xs text-ink-500">Anggaran tahun</p><p class="font-semibold"><Money :value="t.budget" /></p></div>
          <div><p class="text-xs text-ink-500">Realisasi s.d. kini</p><p class="font-semibold"><Money :value="t.actual" /> <span class="text-xs text-ink-400">({{ pctUsed }}%)</span></p></div>
          <div><p class="text-xs text-ink-500">Proyeksi akhir tahun</p><p class="font-semibold"><Money :value="report.forecast.projected_year_end" /></p></div>
          <div><p class="text-xs text-ink-500">Selisih proyeksi</p><p class="font-semibold" :class="report.forecast.variance > 0 ? 'text-stamp-600' : 'text-seal-700'"><Money :value="report.forecast.variance" signed /></p></div>
        </div>
        <p class="px-5 pb-4 text-xs text-ink-400">Proyeksi = realisasi + rata-rata 3 bulan terakhir × sisa bulan. THR & bonus tahunan tidak diproyeksikan otomatis.</p>
      </section>

      <section class="card">
        <header class="card-header"><h2 class="text-base font-semibold">Versi anggaran {{ year }}</h2></header>
        <ul class="divide-y divide-ink-100 dark:divide-ink-800"><li v-for="p in plans" :key="p.id"><Link :href="route('budgets.show', p.id)" class="flex items-center gap-3 px-5 py-3 hover:bg-ink-50 dark:hover:bg-ink-800/50"><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">{{ p.name }}</p><p class="text-xs text-ink-500"><Money :value="p.total" /><span v-if="p.pushed"> · dikirim ke Keuangan {{ p.pushed }}</span></p></div><StatusBadge :status="p.status" /></Link></li>
          <li v-if="!plans.length" class="px-5 py-8 text-center text-sm text-ink-400">Belum ada anggaran tahun ini.</li></ul>
      </section>
    </div>

    <section class="card mt-6 overflow-x-auto">
      <header class="card-header"><h2 class="text-base font-semibold">Per departemen (setahun)</h2></header>
      <table class="data-table"><thead><tr><th>Departemen</th><th class="text-right">Anggaran</th><th class="text-right">Realisasi</th><th class="text-right">Sisa</th><th class="w-48">Terpakai</th></tr></thead>
        <tbody><tr v-for="r in report.rows" :key="r.department_id"><td>{{ r.name }}</td><td class="text-right"><Money :value="r.budget" /></td><td class="text-right"><Money :value="r.actual" /></td><td class="text-right"><Money :value="r.budget - r.actual" /></td>
          <td><div class="h-2 overflow-hidden rounded bg-ink-100 dark:bg-ink-800"><div class="h-full" :class="r.actual > r.budget ? 'bg-stamp-500' : 'bg-seal-500'" :style="{ width: Math.min(100, r.budget ? (r.actual / r.budget) * 100 : 0) + '%' }"></div></div></td></tr>
          <tr v-if="!report.rows.length"><td colspan="5" class="py-8 text-center text-ink-400">Belum ada data.</td></tr></tbody></table>
    </section>

    <section class="card card-body mt-6">
      <h2 class="text-base font-semibold">Simulasi kenaikan gaji</h2><p class="mb-3 text-xs text-ink-500">Dampak biaya bila gaji pokok naik X% (estimasi termasuk BPJS perusahaan). Tidak mengubah data apa pun.</p>
      <div class="flex flex-wrap items-end gap-3">
        <div><label class="label">Kenaikan %</label><input v-model="sim.pct" type="number" step="0.5" class="input num !w-28" /></div>
        <div><label class="label">Departemen</label><select v-model="sim.dept" class="input !w-52"><option value="">Semua</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
        <div><label class="label">Mulai bulan</label><input v-model="sim.from_month" type="number" min="1" max="12" class="input num !w-24" /></div>
        <button class="btn-secondary" @click="go({ pct: sim.pct, dept: sim.dept || undefined, from_month: sim.from_month })">Hitung</button>
      </div>
      <div v-if="props.sim" class="mt-4 overflow-x-auto"><table class="data-table"><thead><tr><th>Departemen</th><th class="text-right">Orang</th><th class="text-right">Upah tetap sekarang</th><th class="text-right">Dampak / bulan</th><th class="text-right">Dampak s.d. Des</th></tr></thead>
        <tbody><tr v-for="r in sim.rows ?? props.sim.rows" :key="r.department"><td>{{ r.department }}</td><td class="num text-right">{{ r.employees }}</td><td class="text-right"><Money :value="r.current_monthly_wage" /></td><td class="text-right"><Money :value="r.monthly_impact" /></td><td class="text-right"><Money :value="r.impact_until_year_end" /></td></tr>
          <tr class="font-semibold"><td colspan="3">Total</td><td class="text-right"><Money :value="props.sim.total_monthly" /></td><td class="text-right"><Money :value="props.sim.total_annual" /></td></tr></tbody></table></div>
    </section>
  </AppLayout>
</template>
