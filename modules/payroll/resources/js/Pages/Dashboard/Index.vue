<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({ tasks: Array, kpi: Object, lastRun: Object, trend: Array, sdmConfigured: Boolean, audit: Object })

const maxCost = computed(() => Math.max(1, ...props.trend.map((t) => t.cost)))
const alerts = computed(() => {
  const a = []
  if (props.kpi.without_contract > 0) a.push({ tone: 'stamp', text: `${props.kpi.without_contract} karyawan aktif belum punya kontrak kompensasi aktif — tidak akan digaji.`, href: route('employees.index', { contract: 'none', status: 'active' }) })
  if (props.audit && !props.audit.ok) a.push({ tone: 'stamp', text: `Rantai audit RUSAK pada entri #${props.audit.broken_at}. Segera selidiki.`, href: route('audit.index') })
  if (!props.sdmConfigured) a.push({ tone: 'amber', text: 'Koneksi SDM belum diatur (SDM_URL/SDM_TOKEN). Data karyawan & absensi tidak akan masuk otomatis.', href: route('integrations.index') })
  if (props.kpi.webhook_failed > 0) a.push({ tone: 'amber', text: `${props.kpi.webhook_failed} webhook belum terkirim/gagal.`, href: route('integrations.index') })
  if (props.kpi.remittances_due > 0) a.push({ tone: 'amber', text: `${props.kpi.remittances_due} setoran pajak/BPJS jatuh tempo ≤ 5 hari.`, href: route('remittances.index', { status: 'pending' }) })
  return a
})
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <template #title>Dashboard</template>

    <div v-if="alerts.length" class="mb-5 space-y-2">
      <Link v-for="(a, i) in alerts" :key="i" :href="a.href" class="flex items-center gap-3 rounded-md border px-4 py-2.5 text-sm"
        :class="a.tone === 'stamp' ? 'border-stamp-300 bg-stamp-50 text-stamp-800 dark:bg-stamp-900/30 dark:text-stamp-200' : 'border-amber-300 bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-200'">
        <span class="font-bold">{{ a.tone === 'stamp' ? '⚠' : '•' }}</span><span>{{ a.text }}</span><span class="ml-auto text-xs underline">Buka</span>
      </Link>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="card card-body"><p class="text-xs uppercase tracking-wider text-ink-500">Karyawan aktif</p><p class="num mt-1 text-3xl font-semibold">{{ kpi.active_employees }}</p><p class="text-xs text-ink-500">{{ kpi.pending_contracts }} usulan kontrak menunggu</p></div>
      <div class="card card-body"><p class="text-xs uppercase tracking-wider text-ink-500">Putaran berjalan</p><p class="num mt-1 text-3xl font-semibold">{{ kpi.open_runs }}</p><p class="text-xs text-ink-500">{{ kpi.funding_waiting }} pengajuan dana di Keuangan</p></div>
      <div class="card card-body"><p class="text-xs uppercase tracking-wider text-ink-500">Putaran terakhir</p>
        <p v-if="lastRun" class="mt-1 text-2xl font-semibold"><Money :value="lastRun.total_net" /></p><p v-else class="mt-1 text-ink-400">Belum ada</p>
        <p v-if="lastRun" class="text-xs text-ink-500">gaji bersih · {{ lastRun.employee_count }} karyawan</p></div>
      <div class="card card-body"><p class="text-xs uppercase tracking-wider text-ink-500">Biaya perusahaan (terakhir)</p>
        <p v-if="lastRun" class="mt-1 text-2xl font-semibold"><Money :value="lastRun.total_employer_cost" /></p><p v-else class="mt-1 text-ink-400">—</p>
        <p v-if="lastRun" class="text-xs text-ink-500">PPh 21 <Money :value="lastRun.total_pph21" /></p></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
      <section class="card lg:col-span-3">
        <header class="card-header"><h2 class="text-base font-semibold">Tugas saya</h2><span class="badge-seal">{{ tasks.length }}</span></header>
        <ul class="divide-y divide-ink-100 dark:divide-ink-800">
          <li v-for="(t, i) in tasks" :key="i"><Link :href="t.url" class="flex items-center gap-3 px-5 py-3 hover:bg-ink-50 dark:hover:bg-ink-800/50">
            <span class="badge-slate w-20 justify-center">{{ t.kind }}</span><span class="min-w-0 flex-1 truncate text-sm font-medium">{{ t.title }}</span><span class="text-xs text-ink-500">{{ t.step }}</span></Link></li>
          <li v-if="!tasks.length" class="px-5 py-10 text-center text-sm text-ink-400">Tidak ada yang menunggu keputusanmu.</li>
        </ul>
      </section>

      <section class="card lg:col-span-2">
        <header class="card-header"><h2 class="text-base font-semibold">Biaya payroll 12 bulan</h2></header>
        <div class="card-body">
          <div v-if="trend.length" class="flex h-40 items-end gap-1.5">
            <div v-for="t in trend" :key="t.label" class="group flex flex-1 flex-col items-center justify-end gap-1" :title="t.label + ': Rp ' + Math.round(t.cost).toLocaleString('id-ID')">
              <div class="w-full rounded-t bg-seal-500/80 group-hover:bg-seal-600" :style="{ height: Math.max(4, (t.cost / maxCost) * 100) + '%' }"></div>
              <span class="num text-[9px] text-ink-500">{{ t.label.slice(0, 2) }}</span>
            </div>
          </div>
          <p v-else class="py-10 text-center text-sm text-ink-400">Grafik muncul setelah ada putaran yang disetujui.</p>
          <div v-if="audit" class="mt-4 flex items-center gap-2 border-t border-ink-100 pt-3 text-xs dark:border-ink-800">
            <span :class="audit.ok ? 'badge-green' : 'badge-stamp'">{{ audit.ok ? 'Rantai audit utuh' : 'Rantai audit rusak' }}</span><span class="text-ink-500">diverifikasi {{ audit.at }} · {{ audit.checked }} entri</span>
          </div>
        </div>
      </section>
    </div>

    <section v-if="lastRun" class="mt-6">
      <Link :href="route('runs.show', lastRun.id)" class="card card-body flex flex-wrap items-center gap-3 hover:border-seal-400">
        <span class="font-mono text-xs text-ink-500">{{ lastRun.number }}</span><span class="font-medium">{{ lastRun.title }}</span><StatusBadge :status="lastRun.status" /><span class="ml-auto text-sm text-seal-600">Buka putaran →</span>
      </Link>
    </section>
  </AppLayout>
</template>
