<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'

defineProps({ files: Array, keep: Number, driver: String, nativeTool: String })

function createBackup() {
  router.post(route('backup.store'))
}
function destroy(file) {
  if (confirm(`Hapus file backup ${file.name}?`)) router.delete(route('backup.destroy', file.name))
}
function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}
</script>

<template>
  <AppLayout>
    <Head title="Backup Data" />
    <template #title>Backup Database</template>
    <template #actions>
      <button @click="createBackup" class="btn-accent btn-sm">Buat Backup Sekarang</button>
    </template>

    <div class="card card-body mb-6 text-sm text-ink-600 dark:text-ink-300">
      <p>Database: <span class="font-mono">{{ driver }}</span> &middot; Tool native: <span class="font-mono">{{ nativeTool || 'tidak ditemukan (memakai mode fallback PHP)' }}</span></p>
      <p class="mt-1 text-xs text-ink-400">Sistem otomatis menyimpan maksimal {{ keep }} backup terakhir (yang lebih lama dihapus otomatis). Backup terjadwal berjalan setiap hari jam 02:00 WIB.</p>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Nama File</th><th>Mode</th><th>Ukuran</th><th>Dibuat</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="f in files" :key="f.name">
              <td class="font-mono text-xs">{{ f.name }}</td>
              <td><span :class="f.mode === 'full' ? 'badge-green' : 'badge-amber'">{{ f.mode === 'full' ? 'Lengkap' : 'Data saja' }}</span></td>
              <td class="num">{{ formatSize(f.size) }}</td>
              <td class="text-ink-400 text-xs">{{ f.modified }}</td>
              <td class="text-right">
                <div class="flex justify-end gap-2">
                  <a :href="route('backup.download', f.name)" class="btn-ghost btn-sm">Unduh</a>
                  <button @click="destroy(f)" class="btn-ghost btn-sm text-red-600">Hapus</button>
                </div>
              </td>
            </tr>
            <tr v-if="!files.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Belum ada backup.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
