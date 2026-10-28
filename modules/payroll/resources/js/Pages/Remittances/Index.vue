<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

defineProps({ remittances: Object, filters: Object })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const label = { pph21: 'PPh Pasal 21', bpjs_kes: 'BPJS Kesehatan', bpjs_tk: 'BPJS Ketenagakerjaan' }
const filter = (s) => router.get(route('remittances.index'), s ? { status: s } : {}, { preserveState: true })
function paid(r) { const ref = prompt('Nomor bukti bayar (NTPN / bukti BPJS):'); if (ref) router.post(route('remittances.paid', r.id), { ref }, { preserveScroll: true }) }
</script>

<template>
  <AppLayout>
    <Head title="Setoran Pajak & BPJS" />
    <template #title>Setoran Pajak &amp; BPJS</template>
    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-ink-300">Kewajiban yang lahir dari setiap putaran: PPh 21 (setor tgl 10, lapor tgl 20 bulan berikutnya), BPJS Kesehatan (tgl 10), BPJS Ketenagakerjaan (tgl 15). Dibayar oleh Keuangan; bukti bayarnya tercatat di sini.</p>
    <div class="mb-4 flex gap-2"><button class="btn-secondary btn-sm" @click="filter('')">Semua</button><button class="btn-secondary btn-sm" @click="filter('pending')">Belum dibayar</button><button class="btn-secondary btn-sm" @click="filter('paid')">Terbayar</button></div>
    <div class="card overflow-x-auto"><table class="data-table">
      <thead><tr><th>Kewajiban</th><th>Masa</th><th>Putaran</th><th class="text-right">Jumlah</th><th>Jatuh tempo</th><th>Status</th><th>Bukti</th><th></th></tr></thead>
      <tbody><tr v-for="r in remittances.data" :key="r.id"><td class="font-medium">{{ label[r.type] }}</td><td class="num text-xs">{{ r.period }}</td><td><Link :href="route('runs.show', r.run_id)" class="text-xs hover:underline">{{ r.run }}</Link></td><td class="text-right"><Money :value="r.amount" /></td>
        <td class="num text-xs" :class="r.overdue ? 'font-semibold text-stamp-600' : ''">{{ r.due }}<span v-if="r.overdue"> · TERLAMBAT</span><span v-if="r.report_due" class="block text-ink-400">lapor {{ r.report_due }}</span></td>
        <td><StatusBadge :status="r.status === 'paid' ? 'closed' : 'pending'" :label="r.status === 'paid' ? 'Terbayar' : 'Belum'" /></td><td class="font-mono text-xs">{{ r.ref ?? '—' }}<p v-if="r.paid_at" class="font-sans text-[10px] text-ink-400">{{ r.paid_at }} · {{ r.paid_by }}</p></td>
        <td class="text-right"><button v-if="r.status === 'pending' && can('remittances.manage')" class="btn-secondary btn-sm" @click="paid(r)">Catat terbayar</button></td></tr>
        <tr v-if="!remittances.data.length"><td colspan="8" class="py-10 text-center text-ink-400">Belum ada kewajiban.</td></tr></tbody></table></div>
    <Pagination :links="remittances.links" />
  </AppLayout>
</template>
