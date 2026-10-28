<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

defineProps({ participations: Array })
const statusLabels = { registered: 'Terdaftar', attended: 'Hadir', absent: 'Absen', completed: 'Selesai' }
const statusBadge = { registered: 'badge-seal', attended: 'badge-green', absent: 'badge-red', completed: 'badge-green' }
</script>

<template>
  <AppLayout>
    <Head title="Pelatihan Saya" />
    <template #title>Pelatihan Saya</template>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Pelatihan</th><th>Tanggal</th><th>Status</th><th>Nilai</th></tr></thead>
          <tbody>
            <tr v-for="p in participations" :key="p.id">
              <td class="font-medium">{{ p.training.title }}</td>
              <td class="num">{{ p.training.start_date }} — {{ p.training.end_date }}</td>
              <td><span :class="statusBadge[p.status]">{{ statusLabels[p.status] }}</span></td>
              <td class="num">{{ p.score ?? '-' }}</td>
            </tr>
            <tr v-if="!participations.length"><td colspan="4" class="py-10 text-center text-sm text-ink-400">Belum terdaftar pelatihan apa pun.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
