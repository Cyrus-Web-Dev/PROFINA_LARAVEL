<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'

defineProps({ documents: Object, expiring: Boolean })

const docTypeLabels = {
  contract: 'Kontrak Kerja', id_card: 'KTP', diploma: 'Ijazah', certificate: 'Sertifikat',
  npwp: 'NPWP', bpjs: 'Kartu BPJS', other: 'Lainnya',
}

function toggleExpiring(value) {
  router.get(route('documents.index'), value ? { expiring: 1 } : {}, { preserveState: true })
}
function destroy(doc) {
  if (confirm(`Hapus dokumen "${doc.title}"?`)) router.delete(route('documents.destroy', doc.id))
}
function fmt(d) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }
</script>

<template>
  <AppLayout>
    <Head title="Dokumen Karyawan" />
    <template #title>Dokumen Karyawan</template>

    <div class="mb-4 flex gap-2">
      <button @click="toggleExpiring(false)" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': !expiring }">Semua Dokumen</button>
      <button @click="toggleExpiring(true)" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': expiring }">Segera Kedaluwarsa</button>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Karyawan</th><th>Dokumen</th><th>Jenis</th><th>Kedaluwarsa</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="doc in documents.data" :key="doc.id">
              <td>
                <Link :href="route('employees.show', doc.employee.id)" class="font-medium hover:underline">{{ doc.employee.full_name }}</Link>
                <p class="text-xs text-ink-400 font-mono">{{ doc.employee.nip }}</p>
              </td>
              <td>{{ doc.title }}</td>
              <td><span class="badge-slate">{{ docTypeLabels[doc.type] || doc.type }}</span></td>
              <td class="num" :class="{ 'text-seal-600 font-medium': expiring }">{{ fmt(doc.expires_at) }}</td>
              <td class="text-right">
                <div class="flex justify-end gap-2">
                  <a :href="route('documents.download', doc.id)" class="btn-ghost btn-sm">Unduh</a>
                  <button @click="destroy(doc)" class="btn-ghost btn-sm text-red-600">Hapus</button>
                </div>
              </td>
            </tr>
            <tr v-if="!documents.data.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Tidak ada dokumen.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="documents.links" /></div>
    </div>
  </AppLayout>
</template>
