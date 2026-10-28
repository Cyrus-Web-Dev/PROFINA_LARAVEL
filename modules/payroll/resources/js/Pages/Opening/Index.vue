<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Money from '@/Components/Money.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({ year: Number, rows: Array })
const form = useForm({ year: props.year, csv: '' })
</script>

<template>
  <AppLayout>
    <Head title="Saldo Awal Tahun" />
    <template #title>Saldo Awal Tahun Berjalan</template>
    <div class="grid gap-6 xl:grid-cols-2">
      <section class="card card-body space-y-3">
        <h2 class="text-base font-semibold">Impor saldo awal</h2>
        <p class="text-sm text-ink-600 dark:text-ink-300">Dipakai bila PAYBRIDGE mulai dipakai <b>di tengah tahun</b> (mis. Oktober). Tanpa ini, PPh 21 masa pajak terakhir (Desember) salah karena Januari–sebelum go-live tidak dikenal. Isi dari bukti potong/rekap payroll sistem lama.</p>
        <div><label class="label">Tahun</label><input v-model="form.year" type="number" class="input num !w-32" /></div>
        <div><label class="label">CSV: nip, bulan_terhitung, bruto_ytd, iuran_pensiun_ytd, pph21_ytd</label><textarea v-model="form.csv" rows="8" class="input font-mono text-xs" placeholder="EMP-0001,9,310500000,6210000,12450000" /></div>
        <button class="btn-primary self-start" :disabled="form.processing" @click="form.post(route('opening.store'), { preserveScroll: true })">Simpan</button>
        <p v-if="form.errors.csv" class="text-sm text-stamp-600">{{ form.errors.csv }}</p>
      </section>
      <section class="card overflow-x-auto"><header class="card-header"><h2 class="text-base font-semibold">Tersimpan tahun {{ year }}</h2></header>
        <table class="data-table"><thead><tr><th>Karyawan</th><th class="text-right">Bulan</th><th class="text-right">Bruto YTD</th><th class="text-right">Pensiun YTD</th><th class="text-right">PPh 21 YTD</th></tr></thead>
          <tbody><tr v-for="r in rows" :key="r.nip"><td>{{ r.name }}<p class="font-mono text-xs text-ink-400">{{ r.nip }}</p></td><td class="num text-right">{{ r.months }}</td><td class="text-right"><Money :value="r.gross" /></td><td class="text-right"><Money :value="r.pension" /></td><td class="text-right"><Money :value="r.tax" /></td></tr>
            <tr v-if="!rows.length"><td colspan="5" class="py-8 text-center text-ink-400">Belum ada.</td></tr></tbody></table></section>
    </div>
  </AppLayout>
</template>
