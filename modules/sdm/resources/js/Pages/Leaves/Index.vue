<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ leaves: Object, leaveTypes: Array, filters: Object })

const statusBadge = { pending: 'badge-amber', approved: 'badge-green', rejected: 'badge-red', cancelled: 'badge-slate' }
const statusLabels = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }

function filterStatus(status) {
  router.get(route('leaves.index'), status ? { status } : {}, { preserveState: true })
}
function approve(leave) {
  if (confirm(`Setujui cuti ${leave.employee.full_name}?`)) router.post(route('leaves.approve', leave.id))
}
function reject(leave) {
  const reason = prompt('Alasan penolakan:')
  if (reason) router.post(route('leaves.reject', leave.id), { rejection_reason: reason })
}
</script>

<template>
  <AppLayout>
    <Head title="Cuti & Izin" />
    <template #title>Cuti &amp; Izin</template>

    <div class="mb-4 flex flex-wrap gap-2">
      <button @click="filterStatus(null)" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': !filters.status }">Semua</button>
      <button @click="filterStatus('pending')" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': filters.status === 'pending' }">Menunggu</button>
      <button @click="filterStatus('approved')" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': filters.status === 'approved' }">Disetujui</button>
      <button @click="filterStatus('rejected')" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': filters.status === 'rejected' }">Ditolak</button>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Karyawan</th><th>Jenis</th><th>Periode</th><th>Hari</th><th>Alasan</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="l in leaves.data" :key="l.id">
              <td class="font-medium">{{ l.employee.full_name }}</td>
              <td>{{ l.leave_type.name }}</td>
              <td class="num">{{ l.start_date }} — {{ l.end_date }}</td>
              <td class="num">{{ l.total_days }}</td>
              <td class="text-ink-500 max-w-xs truncate" :title="l.reason">{{ l.reason }}</td>
              <td><span :class="statusBadge[l.status]">{{ statusLabels[l.status] }}</span></td>
              <td class="text-right">
                <div v-if="l.status === 'pending'" class="flex justify-end gap-2">
                  <button @click="approve(l)" class="btn-secondary btn-sm text-emerald-700">Setujui</button>
                  <button @click="reject(l)" class="btn-ghost btn-sm text-red-600">Tolak</button>
                </div>
              </td>
            </tr>
            <tr v-if="!leaves.data.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Tidak ada pengajuan cuti.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="leaves.links" /></div>
    </div>
  </AppLayout>
</template>
