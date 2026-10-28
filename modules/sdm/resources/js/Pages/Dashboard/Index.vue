<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { Bar, Line } from 'vue-chartjs'
import { Chart as ChartJS, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler } from 'chart.js'

ChartJS.register(BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Filler)

const props = defineProps({
  stats: Object,
  by_department: Array,
  by_employment_type: Array,
  today_attendance: Object,
  upcoming_trainings: Array,
  expiring_documents: Array,
  new_hires_trend: Array,
  upcoming_holidays: { type: Array, default: () => [] },
})

const deptChartData = computed(() => ({
  labels: props.by_department.map(d => d.name ?? 'Tanpa Departemen'),
  datasets: [{ label: 'Karyawan Aktif', data: props.by_department.map(d => d.total), backgroundColor: '#c99a3b', borderRadius: 4, maxBarThickness: 36 }],
}))
const deptChartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }

const trendChartData = computed(() => ({
  labels: props.new_hires_trend.map(t => t.month),
  datasets: [{ label: 'Karyawan Baru', data: props.new_hires_trend.map(t => t.total), borderColor: '#385571', backgroundColor: 'rgba(56,85,113,0.12)', tension: 0.35, fill: true, pointRadius: 3 }],
}))
const trendChartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }

const employmentTypeLabels = { tetap: 'Tetap (PKWTT)', kontrak: 'Kontrak (PKWT)', magang: 'Magang', harian: 'Harian Lepas' }

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <template #title>Dashboard</template>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Karyawan Aktif</p>
        <p class="mt-2 font-mono text-3xl font-bold text-ink-900 dark:text-ink-50">{{ stats.total_active_employees }}</p>
      </div>
      <div class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Hadir Hari Ini</p>
        <p class="mt-2 font-mono text-3xl font-bold text-emerald-700 dark:text-emerald-400">{{ stats.today_present }}</p>
      </div>
      <div class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Cuti Menunggu</p>
        <p class="mt-2 font-mono text-3xl font-bold text-seal-600 dark:text-seal-400">{{ stats.pending_leaves }}</p>
      </div>
      <div class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Alpha Hari Ini</p>
        <p class="mt-2 font-mono text-3xl font-bold text-red-600 dark:text-red-400">{{ stats.today_absent }}</p>
      </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div class="card lg:col-span-2">
        <div class="card-header">
          <h2 class="font-serif text-base font-semibold text-ink-900 dark:text-ink-100">Karyawan per Departemen</h2>
        </div>
        <div class="card-body h-72">
          <Bar v-if="by_department.length" :data="deptChartData" :options="deptChartOptions" />
          <p v-else class="flex h-full items-center justify-center text-sm text-ink-400">Belum ada data.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2 class="font-serif text-base font-semibold text-ink-900 dark:text-ink-100">Tipe Kepegawaian</h2>
        </div>
        <div class="card-body space-y-3">
          <div v-for="t in by_employment_type" :key="t.employment_type" class="flex items-center justify-between text-sm">
            <span class="text-ink-600 dark:text-ink-300">{{ employmentTypeLabels[t.employment_type] ?? t.employment_type }}</span>
            <span class="font-mono font-semibold text-ink-900 dark:text-ink-100">{{ t.total }}</span>
          </div>
          <p v-if="!by_employment_type.length" class="text-sm text-ink-400">Belum ada data.</p>
        </div>
      </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div class="card lg:col-span-2">
        <div class="card-header">
          <h2 class="font-serif text-base font-semibold text-ink-900 dark:text-ink-100">Tren Karyawan Baru (6 Bulan Terakhir)</h2>
        </div>
        <div class="card-body h-64">
          <Line v-if="new_hires_trend.length" :data="trendChartData" :options="trendChartOptions" />
          <p v-else class="flex h-full items-center justify-center text-sm text-ink-400">Belum ada data.</p>
        </div>
      </div>

      <div class="space-y-4">
        <div class="card">
          <div class="card-header">
            <h2 class="font-serif text-sm font-semibold text-ink-900 dark:text-ink-100">Pelatihan Mendatang</h2>
            <Link :href="route('trainings.index')" class="text-xs font-medium text-seal-600 hover:underline dark:text-seal-400">Lihat semua</Link>
          </div>
          <ul class="card-body space-y-3">
            <li v-for="t in upcoming_trainings" :key="t.id" class="text-sm">
              <p class="font-medium text-ink-800 dark:text-ink-100">{{ t.title }}</p>
              <p class="text-xs text-ink-500 dark:text-ink-400">{{ formatDate(t.start_date) }} &middot; {{ t.location || 'Lokasi belum ditentukan' }}</p>
            </li>
            <li v-if="!upcoming_trainings.length" class="text-sm text-ink-400">Tidak ada pelatihan terjadwal.</li>
          </ul>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="font-serif text-sm font-semibold text-ink-900 dark:text-ink-100">Libur Mendatang</h2>
            <Link :href="route('holidays.index')" class="text-xs font-medium text-seal-600 hover:underline dark:text-seal-400">Kalender</Link>
          </div>
          <ul class="card-body space-y-3">
            <li v-for="h in upcoming_holidays" :key="h.date" class="text-sm">
              <p class="font-medium text-ink-800 dark:text-ink-100">{{ h.name }}</p>
              <p class="text-xs text-ink-500 dark:text-ink-400">{{ formatDate(h.date) }}</p>
            </li>
            <li v-if="!upcoming_holidays.length" class="text-sm text-ink-400">Tidak ada libur dalam 6 bulan ke depan.</li>
          </ul>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="font-serif text-sm font-semibold text-ink-900 dark:text-ink-100">Dokumen Segera Berakhir</h2>
            <Link :href="route('documents.index')" class="text-xs font-medium text-seal-600 hover:underline dark:text-seal-400">Lihat semua</Link>
          </div>
          <ul class="card-body space-y-3">
            <li v-for="(d, i) in expiring_documents" :key="i" class="text-sm">
              <p class="font-medium text-ink-800 dark:text-ink-100">{{ d.title }} &middot; {{ d.full_name }}</p>
              <p class="text-xs text-seal-600 dark:text-seal-400">Berakhir {{ formatDate(d.expires_at) }}</p>
            </li>
            <li v-if="!expiring_documents.length" class="text-sm text-ink-400">Tidak ada dokumen yang segera berakhir.</li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
