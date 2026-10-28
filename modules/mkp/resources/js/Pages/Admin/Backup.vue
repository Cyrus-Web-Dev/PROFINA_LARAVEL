<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  files: { type: Array, default: () => [] },
  keep: { type: Number, default: 10 },
  driver: { type: String, default: 'mysql' },
  nativeTool: { type: String, default: null },
})

const form = useForm({})

function createBackup() {
  form.post(route('admin.backup.store'), { preserveScroll: true })
}
function remove(file) {
  if (confirm(`Hapus file backup ${file.name}?`)) {
    router.delete(route('admin.backup.destroy', file.name), { preserveScroll: true })
  }
}
function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(2)} MB`
}
</script>

<template>
  <AppLayout>
    <template #title>Backup Data</template>

    <div class="mx-auto max-w-3xl space-y-6">
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Buat backup database</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              Database: <strong>{{ driver }}</strong> ·
              <span v-if="nativeTool">memakai <code class="break-all">{{ nativeTool }}</code> (backup lengkap: struktur + data)</span>
              <span v-else>mysqldump/pg_dump tidak ditemukan → backup <strong>data saja</strong> (mode PHP)</span>
            </p>
            <p class="mt-1 text-xs text-slate-400">Disimpan {{ keep }} file terbaru; yang lebih lama dihapus otomatis.</p>
          </div>
          <button type="button" :disabled="form.processing" class="shrink-0 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50" @click="createBackup">
            {{ form.processing ? 'Membuat backup…' : 'Buat Backup Sekarang' }}
          </button>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-left text-xs uppercase text-slate-400 dark:border-slate-700">
                <th class="px-4 py-3">File</th>
                <th class="px-4 py-3">Jenis</th>
                <th class="px-4 py-3">Ukuran</th>
                <th class="px-4 py-3">Dibuat</th>
                <th class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in files" :key="f.name" class="border-b border-slate-50 dark:border-slate-700/50">
                <td class="px-4 py-3 font-mono text-xs text-slate-700 dark:text-slate-200">{{ f.name }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="f.mode === 'full' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300'">
                    {{ f.mode === 'full' ? 'Lengkap' : 'Data saja' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-slate-500">{{ formatSize(f.size) }}</td>
                <td class="px-4 py-3 text-slate-500">{{ f.modified }}</td>
                <td class="px-4 py-3 text-right">
                  <a :href="route('admin.backup.download', f.name)" class="mr-3 text-xs font-medium text-emerald-600 hover:underline">Unduh</a>
                  <button type="button" class="text-xs font-medium text-rose-600 hover:underline" @click="remove(f)">Hapus</button>
                </td>
              </tr>
              <tr v-if="files.length === 0">
                <td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada backup. Klik "Buat Backup Sekarang".</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-lg bg-slate-100 p-4 text-xs leading-relaxed text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
        <p class="font-medium text-slate-600 dark:text-slate-300">Penting diketahui</p>
        <ul class="mt-1 list-disc space-y-1 pl-4">
          <li><strong>Salin file backup ke tempat lain</strong> (flashdisk / cloud). Backup yang hanya ada di komputer yang sama ikut hilang bila komputernya rusak.</li>
          <li>File berisi seluruh data keuangan dan hash password, jadi simpan dengan aman. Hanya Manager yang bisa mengunduhnya.</li>
          <li>Foto profil & logo ada di <code>storage/app/private</code> dan <strong>tidak</strong> ikut di file SQL ini — salin foldernya juga.</li>
          <li><strong>Restore sengaja tidak dibuat di web</strong> (satu klik salah bisa menimpa semua data). Lihat README bagian "Backup & restore".</li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
