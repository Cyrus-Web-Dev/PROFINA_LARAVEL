<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  employee: Object, today: Object, balances: Array, pending_leaves: Number,
  latest_payslip: Object, announcements: Array, upcoming_holidays: Array,
})

const hour = new Date().getHours()
const greeting = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 19 ? 'Selamat sore' : 'Selamat malam'

function withLocation(routeName) {
  const post = (data) => router.post(route(routeName), data, { preserveScroll: true })
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((p) => post({ lat: p.coords.latitude, lng: p.coords.longitude }), () => post({}), { timeout: 8000 })
  } else post({})
}
const fmt = (d) => new Date(d).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' })
const rupiah = (n) => 'Rp ' + Number(n ?? 0).toLocaleString('id-ID')
</script>

<template>
  <AppLayout>
    <Head title="Beranda" />
    <template #title>Beranda</template>

    <div v-if="!employee" class="card card-body text-sm text-ink-600 dark:text-ink-300">
      Akunmu belum dihubungkan ke data karyawan, sehingga fitur absen, cuti, dan slip gaji belum bisa dipakai. Hubungi Admin HR.
    </div>

    <template v-else>
      <div class="mb-6">
        <h2 class="font-serif text-2xl font-semibold text-ink-900 dark:text-ink-50">{{ greeting }}, {{ employee.full_name.split(' ')[0] }}</h2>
        <p class="text-sm text-ink-500 dark:text-ink-400">{{ employee.position || 'Karyawan' }} &middot; {{ employee.department || '-' }}</p>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Absensi Hari Ini</p>
          <div class="mt-3 flex flex-wrap items-center gap-6">
            <div><p class="text-xs text-ink-400">Masuk</p><p class="font-mono text-2xl font-bold">{{ today?.check_in?.slice(0,5) || '--:--' }}</p></div>
            <div><p class="text-xs text-ink-400">Pulang</p><p class="font-mono text-2xl font-bold">{{ today?.check_out?.slice(0,5) || '--:--' }}</p></div>
            <span v-if="today?.status === 'terlambat'" class="badge-amber">Terlambat {{ today.late_minutes }} mnt</span>
          </div>
          <div class="mt-4 flex gap-2">
            <button v-if="!today?.check_in" @click="withLocation('attendances.check-in')" class="btn-accent">Check-in</button>
            <button v-else-if="!today?.check_out" @click="withLocation('attendances.check-out')" class="btn-primary">Check-out</button>
            <span v-else class="badge-green">Absensi hari ini lengkap</span>
            <Link :href="route('attendances.my-history')" class="btn-secondary">Riwayat</Link>
          </div>
        </div>

        <div class="card card-body">
          <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Slip Gaji Terakhir</p>
          <template v-if="latest_payslip">
            <p class="mt-2 font-serif text-lg font-semibold">{{ latest_payslip.period }}</p>
            <p class="font-mono text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ rupiah(latest_payslip.net_salary) }}</p>
            <a :href="route('payroll.items.slip', latest_payslip.id)" class="btn-secondary btn-sm mt-3">Unduh PDF</a>
          </template>
          <p v-else class="mt-2 text-sm text-ink-400">Belum ada slip gaji yang terbit.</p>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="card card-body">
          <div class="flex items-center justify-between"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Saldo Cuti</p><Link :href="route('leaves.mine')" class="text-xs text-seal-600 hover:underline">Ajukan</Link></div>
          <div v-for="b in balances" :key="b.leave_type_id" class="mt-3">
            <div class="flex justify-between text-sm"><span>{{ b.name }}</span><span class="font-mono">{{ b.remaining }} / {{ b.quota }}</span></div>
            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full bg-seal-500" :style="{ width: Math.min(100, (b.used / b.quota) * 100) + '%' }"></div></div>
          </div>
          <p v-if="pending_leaves" class="mt-3 text-xs text-seal-700 dark:text-seal-300">{{ pending_leaves }} pengajuan menunggu persetujuan.</p>
        </div>

        <div class="card card-body">
          <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Libur Mendatang</p>
          <ul class="mt-3 space-y-2 text-sm">
            <li v-for="h in upcoming_holidays" :key="h.date" class="flex justify-between gap-2"><span>{{ h.name }}</span><span class="shrink-0 font-mono text-xs text-ink-400">{{ fmt(h.date) }}</span></li>
            <li v-if="!upcoming_holidays.length" class="text-ink-400">-</li>
          </ul>
        </div>

        <div class="card card-body">
          <div class="flex items-center justify-between"><p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Pengumuman</p><Link :href="route('announcements.index')" class="text-xs text-seal-600 hover:underline">Semua</Link></div>
          <div v-for="a in announcements" :key="a.id" class="mt-3 text-sm">
            <p class="font-medium">{{ a.title }}</p>
            <p class="line-clamp-2 text-xs text-ink-500 dark:text-ink-400">{{ a.body }}</p>
          </div>
          <p v-if="!announcements.length" class="mt-3 text-sm text-ink-400">Belum ada pengumuman.</p>
        </div>
      </div>
    </template>
  </AppLayout>
</template>
