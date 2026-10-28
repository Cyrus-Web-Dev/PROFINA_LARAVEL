<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ params: Array, ter: Object, today: String })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const form = useForm({ code: '', value: '', effective_from: props.today, source: '' })
const cat = ref('A')
const fmt = (v) => (typeof v === 'object' ? JSON.stringify(v) : Number(v).toLocaleString('id-ID'))
const prefill = (p) => { form.code = p.code; form.value = JSON.stringify(p.value) }
const grouped = () => props.params.reduce((a, p) => ((a[p.code] ??= []).push(p), a), {})
</script>

<template>
  <AppLayout>
    <Head title="Regulasi" />
    <template #title>Regulasi &amp; Tarif</template>
    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-ink-300">Semua tarif/batas (BPJS, PPh 21, PTKP, lembur, THR) disimpan <b>bertanggal efektif</b> dan <b>tidak pernah ditimpa</b>: putaran lama selalu bisa dihitung ulang dengan aturan yang berlaku saat itu. Contoh: batas upah JP naik tiap 1 Maret.</p>

    <div class="grid gap-6 xl:grid-cols-3">
      <section class="card xl:col-span-2 overflow-x-auto">
        <header class="card-header"><h2 class="text-base font-semibold">Parameter</h2></header>
        <table class="data-table"><thead><tr><th>Kode</th><th>Berlaku sejak</th><th>Nilai</th><th>Dasar hukum</th><th></th></tr></thead>
          <tbody><template v-for="(rows, code) in grouped()" :key="code"><tr v-for="(p, i) in rows" :key="p.id" :class="i > 0 ? 'opacity-60' : ''">
            <td class="font-mono text-xs">{{ i === 0 ? code : '' }}</td><td class="num text-xs">{{ p.effective_from }}</td><td class="max-w-xs break-words font-mono text-xs">{{ fmt(p.value) }}</td><td class="text-xs text-ink-500">{{ p.source }}</td>
            <td><button v-if="can('regulations.manage') && i === 0" class="text-xs text-seal-600 underline" @click="prefill(p)">versi baru</button></td></tr></template></tbody></table>
      </section>

      <div class="space-y-6">
        <section v-if="can('regulations.manage')" class="card card-body space-y-3">
          <h2 class="text-base font-semibold">Terbitkan versi baru</h2>
          <input v-model="form.code" class="input font-mono" placeholder="mis. bpjs.jp.wage_cap" />
          <textarea v-model="form.value" rows="3" class="input font-mono" placeholder='11086300  atau  {"pct":5,"cap_month":500000}' /><p v-if="form.errors.value" class="text-xs text-stamp-600">{{ form.errors.value }}</p>
          <input v-model="form.effective_from" type="date" class="input" />
          <input v-model="form.source" class="input" placeholder="Dasar hukum / nomor surat edaran (wajib)" />
          <button class="btn-primary" :disabled="form.processing" @click="form.post(route('regulations.publish'), { preserveScroll: true })">Terbitkan</button>
          <p class="text-xs text-ink-500">Tercatat di Jejak Audit. Verifikasi dulu ke sumber resmi (DJP/BPJS).</p>
        </section>
        <section class="card">
          <header class="card-header"><h2 class="text-base font-semibold">Tabel TER (PMK 168/2023)</h2><div class="flex gap-1"><button v-for="c in ['A', 'B', 'C']" :key="c" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white': cat === c }" @click="cat = c">{{ c }}</button></div></header>
          <div class="max-h-96 overflow-auto"><table class="data-table"><thead><tr><th class="text-right">Penghasilan bruto</th><th class="text-right">Tarif</th></tr></thead>
            <tbody><tr v-for="(r, i) in ter[cat] ?? []" :key="i"><td class="num text-right text-xs">{{ r[0].toLocaleString('id-ID') }} – {{ r[1] ? r[1].toLocaleString('id-ID') : '∞' }}</td><td class="num text-right">{{ r[2] }}%</td></tr></tbody></table></div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
