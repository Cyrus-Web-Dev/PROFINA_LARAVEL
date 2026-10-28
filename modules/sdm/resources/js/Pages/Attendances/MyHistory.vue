<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ attendances: Array, month: String })

const selectedMonth = ref(props.month)
function changeMonth() {
  router.get(route('attendances.my-history'), { month: selectedMonth.value }, { preserveState: true })
}

const statusBadge = { hadir: 'badge-green', terlambat: 'badge-amber', izin: 'badge-slate', sakit: 'badge-slate', alpha: 'badge-red', cuti: 'badge-seal', wfh: 'badge-green', libur: 'badge-slate' }
const statusLabels = { hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', sakit: 'Sakit', alpha: 'Alpha', cuti: 'Cuti', wfh: 'WFH', libur: 'Libur' }

function checkIn() {
  const opts = { onError: (e) => alert(Object.values(e)[0] || 'Gagal check-in') }
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => router.post(route('attendances.check-in'), { lat: pos.coords.latitude, lng: pos.coords.longitude }, opts),
      () => router.post(route('attendances.check-in'), {}, opts)
    )
  } else {
    router.post(route('attendances.check-in'), {}, opts)
  }
}
function checkOut() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => router.post(route('attendances.check-out'), { lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => router.post(route('attendances.check-out'))
    )
  } else {
    router.post(route('attendances.check-out'))
  }
}
</script>

<template>
  <AppLayout>
    <Head title="Absen Saya" />
    <template #title>Absen Saya</template>

    <div class="card mb-6">
      <div class="card-body flex flex-wrap items-center gap-3">
        <button @click="checkIn" class="btn-accent">Check-in Sekarang</button>
        <button @click="checkOut" class="btn-secondary">Check-out Sekarang</button>
        <p class="text-xs text-ink-400">Lokasi akan dicatat bila kamu mengizinkan akses lokasi browser.</p>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <input v-model="selectedMonth" type="month" class="input max-w-[180px]" @change="changeMonth" />
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Tanggal</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Catatan</th></tr></thead>
          <tbody>
            <tr v-for="a in attendances" :key="a.id">
              <td class="num">{{ a.date }}</td>
              <td class="font-mono">{{ a.check_in?.slice(0,5) || '-' }}</td>
              <td class="font-mono">{{ a.check_out?.slice(0,5) || '-' }}</td>
              <td><span :class="statusBadge[a.status]">{{ statusLabels[a.status] }}</span></td>
              <td class="text-ink-500 text-xs">{{ a.notes || '-' }}</td>
            </tr>
            <tr v-if="!attendances.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Belum ada riwayat absensi bulan ini.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
