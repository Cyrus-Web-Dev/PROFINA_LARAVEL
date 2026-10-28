<script setup>
import { computed, ref } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useCan } from '@/permissions'
import { formatIDR } from '@/utils'

const props = defineProps({ funding: Object, cashAccounts: Array, threshold: Number })
const can = useCan()
const f = computed(() => props.funding)
const decideForm = useForm({ decision: 'approve', note: '', journal_date: '' })
const cashForm = useForm({ cash_account_id: props.cashAccounts[0]?.id ?? '', date: new Date().toISOString().slice(0, 10), method: 'transfer', reference: '' })
const decide = (d) => { decideForm.decision = d; decideForm.post(route('payroll.decide', f.value.id), { preserveScroll: true }) }
const disburse = () => cashForm.post(route('payroll.disburse', f.value.id), { preserveScroll: true })
const refresh = () => router.post(route('payroll.refresh', f.value.id), {}, { preserveScroll: true })
const sumD = computed(() => Object.values(f.value.breakdown.debit ?? {}).reduce((a, b) => a + b, 0))
const sumC = computed(() => Object.values(f.value.breakdown.credit ?? {}).reduce((a, b) => a + b, 0))
const big = computed(() => f.value.net >= props.threshold)
const batchTotal = computed(() => (f.value.bank_batches ?? []).reduce((a, b) => a + b.total_amount, 0))
</script>

<template>
  <AppLayout>
    <template #title>{{ f.title }} <span class="ml-2 font-mono text-sm text-slate-400">{{ f.number }}</span></template>
    <template #actions><button class="rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-slate-200 hover:bg-slate-50" @click="refresh">Muat ulang dari PAYBRIDGE</button></template>

    <div v-if="f.last_error" class="mb-4 rounded-xl bg-rose-50 px-4 py-2 text-sm text-rose-700">{{ f.last_error }}</div>
    <div class="grid gap-6 lg:grid-cols-3">
      <section class="rounded-xl bg-white p-5 ring-1 ring-slate-200 lg:col-span-2">
        <div class="grid gap-3 sm:grid-cols-3">
          <div><p class="text-xs text-slate-500">Utang gaji (akrual)</p><p class="text-xl font-semibold tabular-nums">{{ formatIDR(f.net) }}</p></div>
          <div><p class="text-xs text-slate-500">Ditransfer (terkunci)</p><p class="text-xl font-semibold tabular-nums">{{ formatIDR(f.disbursable) }}</p></div>
          <div><p class="text-xs text-slate-500">Karyawan · periode</p><p class="text-xl font-semibold">{{ f.employees }} · {{ f.period }}</p></div>
        </div>
        <h3 class="mt-6 text-sm font-semibold">Rincian jurnal akrual (total per akun)</h3>
        <table class="mt-2 w-full text-sm"><thead class="text-left text-xs uppercase text-slate-400"><tr><th>Akun</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr></thead><tbody class="divide-y divide-slate-100">
          <tr v-for="(v, k) in f.breakdown.debit" :key="'d' + k"><td>{{ f.accounts[k] }}</td><td class="text-right tabular-nums">{{ formatIDR(v) }}</td><td></td></tr>
          <tr v-for="(v, k) in f.breakdown.credit" :key="'c' + k"><td class="pl-6">{{ f.accounts[k] }}</td><td></td><td class="text-right tabular-nums">{{ formatIDR(v) }}</td></tr>
          <tr class="font-semibold"><td>Total</td><td class="text-right tabular-nums">{{ formatIDR(sumD) }}</td><td class="text-right tabular-nums">{{ formatIDR(sumC) }}</td></tr></tbody></table>
        <p class="mt-1 text-xs" :class="Math.abs(sumD - sumC) > 1 ? 'text-rose-600' : 'text-emerald-700'">{{ Math.abs(sumD - sumC) > 1 ? 'Tidak seimbang — jangan disetujui.' : '✓ Seimbang' }}</p>

        <h3 class="mt-6 text-sm font-semibold">File transfer bank dari Payroll</h3>
        <p class="text-xs text-slate-500">Bandingkan hash dengan file yang kamu unggah ke bank. Beda sedikit saja = file diubah.</p>
        <div v-for="b in f.bank_batches" :key="b.batch_number" class="mt-2 rounded-lg bg-slate-50 p-3 text-xs"><p class="font-mono">{{ b.batch_number }} · {{ b.item_count }} transfer · {{ formatIDR(b.total_amount) }}</p><p class="mt-1 break-all font-mono text-slate-500">SHA-256 {{ b.file_sha256 }}</p></div>
        <p v-if="!(f.bank_batches ?? []).length" class="mt-2 text-sm text-amber-700">Payroll belum membuat file transfer.</p>
        <p v-else-if="Math.abs(batchTotal - f.disbursable) > 0.01" class="mt-2 text-sm text-rose-600">Total file tidak sama dengan nominal yang boleh dicairkan!</p>
        <p v-if="f.snapshot_hash" class="mt-4 break-all font-mono text-[10px] text-slate-400">Snapshot putaran (terkunci di Payroll): {{ f.snapshot_hash }}</p>
      </section>

      <aside class="space-y-6">
        <section v-if="f.status === 'submitted' && can('payroll.decide')" class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
          <h3 class="font-semibold">Keputusan</h3>
          <p v-if="big" class="mt-1 text-xs text-amber-700">Nominal besar: hanya pemegang hak “Setujui jurnal bernilai besar”.</p>
          <label class="mt-3 block text-xs text-slate-500">Tanggal jurnal akrual</label><input v-model="decideForm.journal_date" type="date" class="w-full rounded-lg border-slate-300 text-sm" />
          <label class="mt-3 block text-xs text-slate-500">Catatan (wajib bila menolak/revisi)</label><textarea v-model="decideForm.note" rows="3" class="w-full rounded-lg border-slate-300 text-sm" />
          <p v-if="decideForm.errors.payroll" class="mt-1 text-xs text-rose-600">{{ decideForm.errors.payroll }}</p>
          <div class="mt-3 flex flex-wrap gap-2"><button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white" @click="decide('approve')">Setujui &amp; jurnal</button><button class="rounded-lg bg-violet-100 px-3 py-2 text-sm text-violet-700" @click="decide('revision')">Revisi</button><button class="rounded-lg bg-rose-100 px-3 py-2 text-sm text-rose-700" @click="decide('reject')">Tolak</button></div>
        </section>
        <section v-if="f.status === 'approved' && can('payroll.disburse')" class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
          <h3 class="font-semibold">Cairkan gaji</h3><p class="mt-1 text-xs text-slate-500">Nominal dikunci: <b>{{ formatIDR(f.disbursable) }}</b> (tidak bisa diubah).</p>
          <label class="mt-3 block text-xs text-slate-500">Sumber dana</label><select v-model="cashForm.cash_account_id" class="w-full rounded-lg border-slate-300 text-sm"><option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option></select>
          <label class="mt-3 block text-xs text-slate-500">Tanggal</label><input v-model="cashForm.date" type="date" class="w-full rounded-lg border-slate-300 text-sm" />
          <label class="mt-3 block text-xs text-slate-500">Metode · referensi bank</label><div class="flex gap-2"><input v-model="cashForm.method" class="w-28 rounded-lg border-slate-300 text-sm" /><input v-model="cashForm.reference" class="flex-1 rounded-lg border-slate-300 font-mono text-sm" /></div>
          <p v-if="cashForm.errors.payroll" class="mt-1 text-xs text-rose-600">{{ cashForm.errors.payroll }}</p>
          <button class="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white" :disabled="cashForm.processing" @click="disburse">Cairkan &amp; buat jurnal</button>
        </section>
        <section class="rounded-xl bg-white p-5 text-sm ring-1 ring-slate-200"><h3 class="font-semibold">Status</h3>
          <p class="mt-1">{{ f.status }}</p><p v-if="f.decided_by" class="mt-2 text-xs text-slate-500">Diputuskan {{ f.decided_by }} · {{ f.decided_at }}<br>No. dokumen {{ f.finance_ref }}</p><p v-if="f.decision_note" class="mt-1 text-xs italic">“{{ f.decision_note }}”</p>
          <p v-if="f.disbursed_at" class="mt-2 text-xs text-emerald-700">Cair {{ f.disbursed_at }} · ref {{ f.disbursement_ref ?? '—' }}</p></section>
      </aside>
    </div>
  </AppLayout>
</template>
