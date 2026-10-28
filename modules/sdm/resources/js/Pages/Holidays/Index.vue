<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ year: Number, national: Array, company: Array, sourceStatus: String })
const page = usePage()
const canManage = () => (page.props.auth.user.permissions ?? []).includes('holidays.manage')

const form = useForm({ date: '', name: '', type: 'holiday' })
function submit() {
  form.post(route('holidays.store'), { onSuccess: () => form.reset('date', 'name') })
}
function remove(h) {
  if (confirm(`Hapus "${h.name}"?`)) router.delete(route('holidays.destroy', h.id), { preserveScroll: true })
}
function changeYear(delta) {
  router.get(route('holidays.index'), { year: props.year + delta }, { preserveState: true })
}
function refresh() {
  router.post(route('holidays.refresh'), {}, { preserveScroll: true })
}
function fmt(d) { return new Date(d).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }) }
</script>

<template>
  <AppLayout>
    <Head title="Hari Libur" />
    <template #title>Hari Libur {{ year }}</template>
    <template #actions>
      <button @click="changeYear(-1)" class="btn-secondary btn-sm">&larr; {{ year - 1 }}</button>
      <button @click="changeYear(1)" class="btn-secondary btn-sm">{{ year + 1 }} &rarr;</button>
    </template>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <div class="card">
        <div class="card-header">
          <div>
            <h2 class="font-serif font-semibold">Libur Nasional &amp; Cuti Bersama</h2>
            <p class="mt-0.5 text-xs text-ink-400">Sumber: {{ sourceStatus }}</p>
          </div>
          <button v-if="canManage()" @click="refresh" class="btn-secondary btn-sm">Segarkan</button>
        </div>
        <ul class="card-body space-y-2 text-sm">
          <li v-for="h in national" :key="h.date" class="flex justify-between gap-3">
            <span class="text-ink-800 dark:text-ink-100">{{ h.name }}</span>
            <span class="shrink-0 text-xs text-ink-400">{{ fmt(h.date) }}</span>
          </li>
          <li v-if="!national.length" class="text-ink-400">Data tahun ini belum tersedia dari sumber.</li>
        </ul>
        <p class="border-t border-ink-100 px-5 py-3 text-xs text-ink-400 dark:border-ink-800">Data berasal dari dataset publik (bukan API resmi pemerintah). Selalu cocokkan dengan SKB 3 Menteri; koreksi dengan menambah "hari kerja pengganti" di kolom kanan.</p>
      </div>

      <div class="space-y-6">
        <form v-if="canManage()" @submit.prevent="submit" class="card card-body space-y-3">
          <h2 class="font-serif font-semibold">Tambah Tanggal Khusus Perusahaan</h2>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div><label class="label">Tanggal</label><input v-model="form.date" type="date" class="input" required /><p v-if="form.errors.date" class="error-text">{{ form.errors.date }}</p></div>
            <div><label class="label">Jenis</label>
              <select v-model="form.type" class="input">
                <option value="holiday">Libur tambahan perusahaan</option>
                <option value="working_day">Tetap hari kerja (batalkan libur nasional)</option>
              </select>
            </div>
          </div>
          <div><label class="label">Keterangan</label><input v-model="form.name" class="input" required /></div>
          <button type="submit" :disabled="form.processing" class="btn-accent btn-sm">Simpan</button>
        </form>

        <div class="card">
          <div class="card-header"><h2 class="font-serif font-semibold">Tanggal Khusus Perusahaan</h2></div>
          <ul class="card-body space-y-2 text-sm">
            <li v-for="h in company" :key="h.id" class="flex items-center justify-between gap-3">
              <div><p class="text-ink-800 dark:text-ink-100">{{ h.name }} <span :class="h.type === 'holiday' ? 'badge-seal' : 'badge-amber'" class="ml-1">{{ h.type === 'holiday' ? 'Libur' : 'Hari kerja' }}</span></p><p class="text-xs text-ink-400">{{ fmt(h.date) }}</p></div>
              <button v-if="canManage()" @click="remove(h)" class="btn-ghost btn-sm text-red-600">Hapus</button>
            </li>
            <li v-if="!company.length" class="text-ink-400">Belum ada.</li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
