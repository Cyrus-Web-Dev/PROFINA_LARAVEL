<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'

const props = defineProps({ runs: Array, employees: Array, year: Number })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const year = ref(props.year), month = ref(''), emp = ref('')
const q = () => (month.value ? `year=${year.value}&month=${month.value}` : `year=${year.value}`)
</script>

<template>
  <AppLayout>
    <Head title="Laporan" />
    <template #title>Laporan</template>
    <div class="mb-5 flex flex-wrap items-end gap-3 card card-body">
      <div><label class="label">Tahun</label><input v-model="year" type="number" class="input num !w-28" /></div>
      <div><label class="label">Bulan (opsional)</label><select v-model="month" class="input !w-40"><option value="">Setahun</option><option v-for="m in 12" :key="m" :value="m">{{ m }}</option></select></div>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article class="card card-body"><h2 class="font-semibold">Rekap PPh Pasal 21</h2><p class="mt-1 text-sm text-ink-500">Per karyawan per masa: bruto kena pajak, metode (TER/final), kategori & tarif. Dasar penyusunan bukti potong; cocokkan dengan template Coretax terbaru sebelum impor.</p><a v-if="can('reports.export')" :href="route('reports.pph21') + '?' + q()" class="btn-primary btn-sm mt-4 self-start">Unduh Excel</a></article>
      <article class="card card-body"><h2 class="font-semibold">Rekap BPJS</h2><p class="mt-1 text-sm text-ink-500">Iuran Kesehatan, JHT, JKK, JKM, JP — bagian karyawan & perusahaan per orang.</p><a v-if="can('reports.export')" :href="route('reports.bpjs') + '?' + q()" class="btn-primary btn-sm mt-4 self-start">Unduh Excel</a></article>
      <article class="card card-body"><h2 class="font-semibold">Biaya per departemen</h2><p class="mt-1 text-sm text-ink-500">Biaya perusahaan (bruto + BPJS perusahaan) per departemen per bulan.</p><a v-if="can('reports.export')" :href="route('reports.departments') + '?year=' + year" class="btn-primary btn-sm mt-4 self-start">Unduh Excel</a></article>
      <article class="card card-body md:col-span-2"><h2 class="font-semibold">Ringkasan PPh 21 tahunan per karyawan (PDF)</h2><p class="mt-1 text-sm text-ink-500">Dokumen kerja internal, bukan pengganti BPA1 resmi dari Coretax.</p>
        <div class="mt-3 flex flex-wrap gap-2"><select v-model="emp" class="input !w-72"><option value="">— pilih karyawan —</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.nip }} · {{ e.full_name }}</option></select>
          <a v-if="emp && can('reports.export')" :href="route('reports.annual-tax') + `?employee_id=${emp}&year=${year}`" class="btn-primary btn-sm">Unduh PDF</a></div></article>
      <article class="card card-body"><h2 class="font-semibold">Register per putaran</h2><p class="mt-1 text-sm text-ink-500">Dari halaman putaran gaji → “Ekspor register”.</p><ul class="mt-2 space-y-1 text-sm"><li v-for="r in runs.slice(0, 5)" :key="r.id"><a :href="route('runs.show', r.id)" class="text-seal-700 underline">{{ r.title }}</a></li></ul></article>
    </div>
    <p class="mt-4 text-xs text-ink-400">Setiap unduhan laporan dicatat di Jejak Audit.</p>
  </AppLayout>
</template>
