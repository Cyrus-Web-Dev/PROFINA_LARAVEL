<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'

defineProps({ files: Array, keep: Number, driver: String, nativeTool: String })
const kb = (b) => (b / 1024 / 1024 >= 1 ? (b / 1024 / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB')
function create() { router.post(route('backup.store'), {}, { preserveScroll: true }) }
function remove(f) { if (confirm(`Hapus backup ${f.name}?`)) router.delete(route('backup.destroy', f.name), { preserveScroll: true }) }
</script>

<template>
  <AppLayout>
    <Head title="Backup Data" />
    <template #title>Backup Data</template>
    <template #actions><button class="btn-primary btn-sm" @click="create">Buat backup sekarang</button></template>

    <div class="card">
      <div class="card-body text-sm text-ink-600 dark:text-ink-300">
        Driver: <span class="font-mono">{{ driver }}</span> · menyimpan {{ keep }} backup terbaru ·
        {{ nativeTool ? 'memakai pg_dump (' + nativeTool + ')' : 'mode PHP (tanpa pg_dump)' }}.
        Jadwal otomatis harian pukul 02:00. Simpan salinan backup di luar server; <b>backup berisi data gaji &amp; rekening</b>.
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>File</th><th>Mode</th><th>Ukuran</th><th>Dibuat</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="f in files" :key="f.name">
              <td class="font-mono text-xs">{{ f.name }}</td><td>{{ f.mode }}</td><td class="num">{{ kb(f.size) }}</td><td class="num">{{ f.modified }}</td>
              <td class="space-x-2 text-right"><a :href="route('backup.download', f.name)" class="btn-secondary btn-sm">Unduh</a><button class="btn-ghost btn-sm" @click="remove(f)">Hapus</button></td>
            </tr>
            <tr v-if="!files.length"><td colspan="5" class="py-8 text-center text-ink-400">Belum ada backup.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
