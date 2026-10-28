<script setup>
import { router, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { useCan } from '@/permissions'
import { formatIDR } from '@/utils'

const props = defineProps({ tab: String, funds: Object, remittances: Object, counts: Object, cashAccounts: Array, events: Array, connected: Boolean, lastSync: String })
const can = useCan()
const STATUS = {
  submitted: ['Menunggu keputusan', 'bg-amber-100 text-amber-700'], approved: ['Disetujui, belum cair', 'bg-teal-100 text-teal-700'], revision: ['Diminta revisi', 'bg-violet-100 text-violet-700'],
  rejected: ['Ditolak', 'bg-rose-100 text-rose-600'], disbursed: ['Gaji cair', 'bg-sky-100 text-sky-700'], closed: ['Selesai', 'bg-emerald-100 text-emerald-700'], cancelled: ['Dibatalkan Payroll', 'bg-slate-100 text-slate-600'],
}
const TABS = [['waiting', 'Menunggu keputusan', 'waiting'], ['cash', 'Siap dicairkan', 'cash'], ['done', 'Sudah cair'], ['remit', 'Setoran pajak & BPJS', 'remit'], ['all', 'Semua']]
const go = (t) => router.get(route('payroll.index'), { tab: t }, { preserveState: true })
const sync = () => router.post(route('payroll.sync'), {}, { preserveScroll: true })

const paying = ref(null)
const pf = useForm({ cash_account_id: props.cashAccounts[0]?.id ?? '', date: new Date().toISOString().slice(0, 10), reference: '' })
const pay = () => pf.post(route('payroll.remittance.pay', paying.value.id), { preserveScroll: true, onSuccess: () => { paying.value = null; pf.reset('reference') } })
</script>

<template>
  <AppLayout>
    <template #title>Dana Payroll</template>
    <template #actions><button v-if="can('payroll.decide') || can('payroll.disburse')" class="rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-slate-200 hover:bg-slate-50" @click="sync">Sinkron dari PAYBRIDGE</button></template>

    <div v-if="!connected" class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">Integrasi PAYBRIDGE belum diatur. Isi <code class="font-mono">PAYROLL_URL</code>, <code class="font-mono">PAYROLL_TOKEN</code>, <code class="font-mono">PAYROLL_WEBHOOK_SECRET</code> di <code class="font-mono">.env</code>, lalu <code class="font-mono">php artisan config:clear</code>.</div>
    <p class="mb-4 max-w-3xl text-sm text-slate-600">Payroll mengajukan dana setelah gaji disetujui &amp; dikunci. Keuangan hanya melihat <b>total per akun</b>, bukan gaji per orang. Menyetujui membuat <b>jurnal akrual</b>; mencairkan membuat <b>jurnal kas</b> dengan nominal yang <b>terkunci</b> sama dengan file transfer bank.</p>

    <div class="mb-4 flex flex-wrap gap-2">
      <button v-for="[k, l, n] in TABS" :key="k" class="rounded-lg px-3 py-1.5 text-sm ring-1" :class="tab === k ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'" @click="go(k)">{{ l }}<span v-if="n && counts[n]" class="ml-1.5 rounded-full bg-orange-500 px-1.5 text-[10px] font-bold text-white">{{ counts[n] }}</span></button>
    </div>

    <div v-if="funds" class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Pengajuan</th><th>Periode</th><th>Status</th><th class="text-right">Utang gaji</th><th class="text-right">Ditransfer</th><th class="px-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="f in funds.data" :key="f.id"><td class="px-4 py-3"><Link :href="route('payroll.show', f.id)" class="font-medium text-slate-900 hover:underline">{{ f.title }}</Link><p class="font-mono text-xs text-slate-400">{{ f.number }} · {{ f.employees }} karyawan</p></td>
            <td class="tabular-nums">{{ f.period }}</td><td><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS[f.status]?.[1]">{{ STATUS[f.status]?.[0] ?? f.status }}</span></td>
            <td class="text-right tabular-nums">{{ formatIDR(f.net) }}</td><td class="text-right tabular-nums">{{ formatIDR(f.disbursable) }}</td><td class="px-4 text-right"><Link :href="route('payroll.show', f.id)" class="text-sm text-emerald-700 hover:underline">Buka</Link></td></tr>
          <tr v-if="!funds.data.length"><td colspan="6" class="py-10 text-center text-slate-400">Tidak ada pengajuan.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination v-if="funds" :links="funds.links" />

    <div v-if="remittances" class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Kewajiban</th><th>Masa</th><th class="text-right">Jumlah</th><th>Jatuh tempo</th><th>Status</th><th class="px-4"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="r in remittances.data" :key="r.id"><td class="px-4 py-3 font-medium">{{ r.label }}<p class="font-mono text-xs text-slate-400">{{ r.run }}</p></td><td class="tabular-nums">{{ r.period }}</td><td class="text-right tabular-nums">{{ formatIDR(r.amount) }}</td>
            <td :class="r.overdue ? 'font-semibold text-rose-600' : ''">{{ r.due }}<span v-if="r.overdue"> · terlambat</span></td><td><span class="rounded-full px-2 py-0.5 text-xs" :class="r.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ r.status === 'paid' ? 'Terbayar ' + (r.ref ?? '') : 'Belum dibayar' }}</span></td>
            <td class="px-4 text-right"><button v-if="r.status === 'pending' && can('payroll.disburse')" class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-medium text-white hover:bg-emerald-700" @click="paying = r">Bayar &amp; jurnal</button></td></tr>
          <tr v-if="!remittances.data.length"><td colspan="6" class="py-10 text-center text-slate-400">Belum ada kewajiban setoran.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination v-if="remittances" :links="remittances.links" />

    <div v-if="paying" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="paying = null">
      <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <h3 class="font-semibold">Setor {{ paying.label }} — {{ paying.period }}</h3><p class="mt-1 text-2xl font-semibold">{{ formatIDR(paying.amount) }}</p>
        <p class="mt-1 text-xs text-slate-500">Jurnal: Dr Utang {{ paying.label }} / Kr Kas-Bank. Nominal dikunci sesuai kewajiban dari Payroll.</p>
        <div class="mt-4 space-y-3"><div><label class="text-xs text-slate-500">Sumber dana</label><select v-model="pf.cash_account_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option></select></div>
          <div><label class="text-xs text-slate-500">Tanggal bayar</label><input v-model="pf.date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" /></div>
          <div><label class="text-xs text-slate-500">Nomor bukti (NTPN / bukti BPJS)</label><input v-model="pf.reference" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm" /><p v-if="pf.errors.reference" class="text-xs text-rose-600">{{ pf.errors.reference }}</p><p v-if="pf.errors.payroll" class="text-xs text-rose-600">{{ pf.errors.payroll }}</p></div></div>
        <div class="mt-5 flex justify-end gap-2"><button class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" @click="paying = null">Batal</button><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white" :disabled="pf.processing" @click="pay">Bayar</button></div>
      </div>
    </div>

    <details v-if="events.length" class="mt-6 text-xs text-slate-500"><summary class="cursor-pointer">Kejadian terakhir dari PAYBRIDGE</summary><ul class="mt-2 space-y-1"><li v-for="(e, i) in events" :key="i"><span class="font-mono">{{ e.when }}</span> {{ e.event }} — {{ e.status }}<span v-if="e.note"> ({{ e.note }})</span></li></ul></details>
  </AppLayout>
</template>
