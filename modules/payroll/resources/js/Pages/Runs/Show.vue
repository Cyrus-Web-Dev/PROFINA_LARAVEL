<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import ControlRail from '@/Components/ControlRail.vue'
import Money from '@/Components/Money.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, Link, usePage } from '@inertiajs/vue3'

const props = defineProps({ run: Object, items: Object, findings: Array, integrity: Array, funding: Object, batches: Array, remittances: Array, can: Object })
const perms = computed(() => usePage().props.auth.user.permissions ?? [])
const tab = ref('summary')
const note = ref('')
const q = ref('')
const post = (name, id, data = {}) => router.post(route(name, id), data, { preserveScroll: true })

// ---- rel kontrol ----
const rail = computed(() => {
  const r = props.run, s = r.steps, st = (i) => s[i]
  const stepState = (x) => (!x ? 'todo' : x.status === 'approved' ? 'done' : x.status === 'rejected' ? 'rejected' : 'todo')
  const out = [
    { label: 'Dihitung', who: r.employee_count ? r.employee_count + ' karyawan' : '', at: r.calculated_at, state: r.calculated_at ? 'done' : 'current' },
    { label: 'Diverifikasi', who: st(0)?.by, at: st(0)?.at, state: stepState(st(0)) },
    { label: 'Disetujui' + (r.dual ? ' (2 orang)' : ''), who: [st(1)?.by, st(2)?.by].filter(Boolean).join(' + '), at: (st(2) ?? st(1))?.at, state: r.dual ? (stepState(st(1)) === 'done' && stepState(st(2)) === 'done' ? 'done' : stepState(st(1)) === 'rejected' || stepState(st(2)) === 'rejected' ? 'rejected' : 'todo') : stepState(st(1)) },
    { label: 'Dicairkan Keuangan', who: props.funding?.by, at: props.funding?.disbursed_at, state: ['disbursed', 'confirmed'].includes(props.funding?.status) ? 'done' : props.funding?.status === 'rejected' ? 'rejected' : 'todo' },
    { label: 'Ditutup', who: '', at: '', state: r.status === 'closed' ? 'done' : 'todo' },
  ]
  if (r.status === 'rejected') out[1].state = 'rejected'
  const first = out.findIndex((x) => x.state === 'todo')
  if (first >= 0 && !['cancelled', 'rejected'].includes(r.status)) out[first].state = 'current'
  return out
})

const blocking = computed(() => props.findings.filter((f) => f.blocking && f.status === 'open'))
const canResolve = (f) => f.status === 'open' && !props.run.locked && (f.code === 'BUDGET_EXCEEDED' ? props.can.resolveBudget : props.can.resolve)
const sevOrder = { critical: 0, warning: 1, info: 2 }

// ---- detail item ----
const detail = ref(null)
async function openItem(i) {
  detail.value = { loading: true, name: i.name }
  const r = await fetch(route('runs.item', i.id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  detail.value = { ...(await r.json()).item, name: i.name, id: i.id }
}
function hold(i) { const reason = prompt('Alasan menahan gaji ' + i.name + ' (min. 10 karakter):'); if (reason) post('runs.hold', i.id, { reason }) }

// ---- temuan ----
const resolveNote = ref({})
function resolve(f, status) {
  const n = (resolveNote.value[f.id] || '').trim()
  if (n.length < 15) return alert('Catatan penjelasan minimal 15 karakter (apa buktinya, siapa yang diverifikasi).')
  post('findings.resolve', f.id, { status, note: n })
}

// ---- keputusan ----
function decide(d) {
  if (d === 'rejected' && !note.value.trim()) return alert('Isi alasan pengembalian.')
  post('runs.decide', props.run.id, { decision: d, note: note.value })
  note.value = ''
}
function cancel() { const reason = prompt('Alasan membatalkan putaran (min. 10 karakter):'); if (reason) post('runs.cancel', props.run.id, { reason }) }
function reconcile(b, ev) { const file = ev.target.files[0]; if (file) router.post(route('batches.reconcile', b.id), { file }, { forceFormData: true, preserveScroll: true }); ev.target.value = '' }
const search = () => router.get(route('runs.show', props.run.id), { q: q.value }, { preserveState: true, preserveScroll: true })
const glLabel = { salary_expense: 'Beban gaji', allowance_expense: 'Beban tunjangan', overtime_expense: 'Beban lembur', bonus_expense: 'Beban bonus', thr_expense: 'Beban THR', reimbursement_expense: 'Reimburse',
  bpjs_kes_company_expense: 'Beban BPJS Kes (perusahaan)', bpjs_tk_company_expense: 'Beban BPJS TK (perusahaan)', net_pay_liability: 'Utang gaji', pph21_liability: 'Utang PPh 21', bpjs_kes_liability: 'Utang BPJS Kes',
  bpjs_tk_liability: 'Utang BPJS TK', employee_receivable: 'Piutang karyawan (kasbon)', other_deduction_payable: 'Utang potongan lain' }
const tabs = computed(() => [['summary', 'Ringkasan'], ['items', `Karyawan (${props.run.employee_count})`], ['findings', `Temuan (${props.findings.filter((f) => f.status === 'open' && f.severity !== 'info').length})`], ['money', 'Keuangan & Pembayaran']])
</script>

<template>
  <AppLayout>
    <Head :title="run.number" />
    <template #title><span class="font-mono text-sm text-ink-400">{{ run.number }}</span> {{ run.title }} <StatusBadge :status="run.status" class="ml-2 align-middle" /> <span v-if="run.locked" class="ml-1 text-seal-600" title="Terkunci">🔒</span></template>
    <template #actions>
      <button v-if="can.calculate" class="btn-secondary btn-sm" @click="post('runs.calculate', run.id)">{{ run.calculated_at ? 'Hitung ulang' : 'Hitung' }}</button>
      <button v-if="can.submit" class="btn-primary btn-sm" @click="post('runs.submit', run.id)">Ajukan verifikasi</button>
      <a v-if="perms.includes('reports.export') && run.employee_count" :href="route('runs.export', run.id)" class="btn-secondary btn-sm">Ekspor register</a>
      <button v-if="can.cancel" class="btn-ghost btn-sm" @click="cancel">Batalkan</button>
    </template>

    <ControlRail class="mb-5" :steps="rail" />

    <div v-if="integrity.length" class="mb-4 rounded-md border border-stamp-400 bg-stamp-50 px-4 py-3 text-sm text-stamp-900"><p class="font-semibold">⚠ Verifikasi integritas GAGAL — jangan disetujui</p><ul class="mt-1 list-disc pl-5"><li v-for="(e, i) in integrity.slice(0, 5)" :key="i">{{ e }}</li></ul></div>
    <div v-if="run.meta.sdm_error" class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900">Penarikan data SDM gagal saat menghitung: {{ run.meta.sdm_error }}</div>
    <div v-if="run.rejection_note && ['rejected', 'cancelled'].includes(run.status)" class="mb-4 rounded-md border border-stamp-300 bg-stamp-50 px-4 py-2 text-sm text-stamp-800">Catatan: {{ run.rejection_note }}</div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <div class="card card-body"><p class="text-[11px] uppercase tracking-wider text-ink-500">Penghasilan bruto</p><p class="mt-1 text-lg font-semibold"><Money :value="run.totals.gross" /></p></div>
      <div class="card card-body"><p class="text-[11px] uppercase tracking-wider text-ink-500">PPh 21</p><p class="mt-1 text-lg font-semibold"><Money :value="run.totals.pph21" /></p></div>
      <div class="card card-body"><p class="text-[11px] uppercase tracking-wider text-ink-500">BPJS (K / P)</p><p class="mt-1 text-sm font-semibold"><Money :value="run.totals.bpjs_employee" /> / <Money :value="run.totals.bpjs_company" /></p></div>
      <div class="card card-body border-seal-300 bg-seal-50 dark:bg-seal-900/20"><p class="text-[11px] uppercase tracking-wider text-seal-700">Gaji bersih</p><p class="mt-1 text-lg font-semibold"><Money :value="run.totals.net" /></p><p v-if="run.totals.disbursable !== run.totals.net" class="text-[11px] text-ink-500">ditransfer <Money :value="run.totals.disbursable" /> (ada yang ditahan)</p></div>
      <div class="card card-body"><p class="text-[11px] uppercase tracking-wider text-ink-500">Biaya perusahaan</p><p class="mt-1 text-lg font-semibold"><Money :value="run.totals.employer_cost" /></p></div>
    </div>

    <div class="mb-4 flex flex-wrap gap-1 border-b border-ink-200 dark:border-ink-800">
      <button v-for="[k, l] in tabs" :key="k" class="-mb-px border-b-2 px-4 py-2 text-sm font-medium" :class="tab === k ? 'border-ink-900 text-ink-900 dark:border-seal-400 dark:text-white' : 'border-transparent text-ink-500 hover:text-ink-800'" @click="tab = k">{{ l }}</button>
    </div>

    <!-- RINGKASAN -->
    <div v-show="tab === 'summary'" class="grid gap-6 lg:grid-cols-3">
      <section class="card lg:col-span-2 overflow-x-auto">
        <header class="card-header"><h2 class="text-base font-semibold">Per departemen</h2></header>
        <table class="data-table"><thead><tr><th>Departemen</th><th class="text-right">Orang</th><th class="text-right">Bruto</th><th class="text-right">Bersih</th><th class="text-right">Biaya perusahaan</th></tr></thead>
          <tbody><tr v-for="d in run.by_department" :key="d.department"><td>{{ d.department }}</td><td class="num text-right">{{ d.count }}</td><td class="text-right"><Money :value="d.gross" /></td><td class="text-right"><Money :value="d.net" /></td><td class="text-right"><Money :value="d.employer_cost" /></td></tr></tbody></table>
        <div v-if="run.budget?.has_plan" class="border-t border-ink-100 p-4 dark:border-ink-800">
          <p class="mb-2 text-sm font-semibold">Pengendalian anggaran bulan ini (anggaran v{{ run.budget.plan.version }})</p>
          <div v-for="b in run.budget.rows" :key="b.department_id" class="mb-2"><div class="flex justify-between text-xs"><span>{{ b.name }}</span><span class="num" :class="b.over > 0 ? 'font-semibold text-stamp-600' : ''">{{ b.pct ?? '—' }}%</span></div>
            <div class="h-2 overflow-hidden rounded bg-ink-100 dark:bg-ink-800"><div class="h-full" :class="b.over > 0 ? 'bg-stamp-500' : 'bg-seal-500'" :style="{ width: Math.min(100, b.pct ?? 0) + '%' }"></div></div></div>
        </div>
        <p v-else-if="run.budget" class="border-t border-ink-100 p-4 text-sm text-amber-800 dark:border-ink-800">Belum ada anggaran SDM aktif untuk tahun ini — pengendalian anggaran tidak berjalan.</p>
      </section>

      <aside class="space-y-6">
        <section class="card card-body">
          <h2 class="mb-3 text-base font-semibold">Persetujuan</h2>
          <template v-if="can.decide">
            <p class="mb-2 text-sm">Langkahmu: <b>{{ can.decide }}</b></p>
            <p v-if="blocking.length" class="mb-2 rounded bg-stamp-50 px-3 py-2 text-xs text-stamp-800">{{ blocking.length }} temuan kritis belum selesai — persetujuan akan ditolak sistem.</p>
            <textarea v-model="note" rows="3" class="input" placeholder="Catatan (wajib bila mengembalikan)" />
            <div class="mt-3 flex gap-2"><button class="btn-accent flex-1" @click="decide('approved')">Setujui</button><button class="btn-danger" @click="decide('rejected')">Kembalikan</button></div>
          </template>
          <p v-else class="text-sm text-ink-500">{{ ['in_review', 'verified'].includes(run.status) ? 'Menunggu pemeriksa/penyetuju lain. Pembuat, penghitung, dan pengaju tidak boleh memutuskan putaran ini.' : 'Tidak ada keputusan yang menunggu.' }}</p>
          <ul class="mt-4 space-y-2 border-t border-ink-100 pt-3 text-sm dark:border-ink-800"><li v-for="s in run.steps" :key="s.step"><span class="font-medium">{{ s.label }}</span> <StatusBadge :status="s.status" /><p v-if="s.by" class="text-xs text-ink-500">{{ s.by }} · {{ s.at }}</p><p v-if="s.note" class="text-xs italic">“{{ s.note }}”</p></li></ul>
        </section>
        <section v-if="run.snapshot_hash" class="card card-body text-xs"><p class="font-semibold">Hash snapshot (terkunci)</p><p class="mt-1 break-all font-mono text-[10px] text-ink-500">{{ run.snapshot_hash }}</p><p class="mt-2 text-ink-500">Isi putaran ini dikunci di database; perubahan angka akan ditolak.</p></section>
      </aside>
    </div>

    <!-- KARYAWAN -->
    <section v-show="tab === 'items'" class="card">
      <div class="flex flex-wrap items-center gap-2 border-b border-ink-100 p-3 dark:border-ink-800"><input v-model="q" class="input !w-64" placeholder="Cari nama / NIP / departemen…" @keyup.enter="search" /><button class="btn-secondary btn-sm" @click="search">Cari</button></div>
      <div class="overflow-x-auto"><table class="data-table">
        <thead><tr><th>Karyawan</th><th class="text-right">Bruto</th><th class="text-right">BPJS (K)</th><th class="text-right">PPh 21</th><th class="text-right">Potongan lain</th><th class="text-right">Bersih</th><th></th></tr></thead>
        <tbody><tr v-for="i in items.data" :key="i.id" :class="i.held ? 'bg-amber-50/60 dark:bg-amber-900/10' : ''">
          <td><button class="text-left font-medium hover:underline" @click="openItem(i)">{{ i.name }}</button><p class="font-mono text-xs text-ink-400">{{ i.nip }} · {{ i.department }}</p><p v-if="i.held" class="text-xs text-amber-700">DITAHAN: {{ i.held_reason }}</p></td>
          <td class="text-right"><Money :value="i.gross" /></td><td class="text-right"><Money :value="i.bpjs_employee" muted /></td><td class="text-right"><Money :value="i.pph21" /><p class="text-[10px] uppercase text-ink-400">{{ i.method }}</p></td><td class="text-right"><Money :value="i.other" muted /></td><td class="text-right font-semibold"><Money :value="i.net" /></td>
          <td class="whitespace-nowrap text-right"><span v-if="i.status === 'paid'" class="badge-green">Terbayar</span><span v-else-if="i.status === 'failed'" class="badge-stamp">Gagal</span>
            <button v-if="can.hold && !i.held && i.status !== 'paid'" class="btn-ghost btn-sm" @click="hold(i)">Tahan</button><button v-if="can.hold && i.held" class="btn-ghost btn-sm" @click="post('runs.release', i.id)">Lepas</button></td></tr>
          <tr v-if="!items.data.length"><td colspan="7" class="py-10 text-center text-ink-400">Belum ada item. Klik “Hitung”.</td></tr></tbody></table></div>
      <div class="p-3"><Pagination :links="items.links" /></div>
    </section>

    <!-- TEMUAN -->
    <section v-show="tab === 'findings'" class="space-y-3">
      <p class="max-w-3xl text-sm text-ink-600 dark:text-ink-300">Mesin kontrol memeriksa karyawan fiktif, rekening ganda/baru diubah, absensi kosong, lonjakan gaji, nominal bulat, lembur tak sesuai absensi, dan anggaran. <b>Temuan kritis</b> harus dijelaskan oleh orang yang bukan pembuat putaran sebelum bisa disetujui.</p>
      <article v-for="f in [...findings].sort((a, b) => sevOrder[a.severity] - sevOrder[b.severity])" :key="f.id" class="card card-body" :class="f.severity === 'critical' && f.status === 'open' ? 'border-stamp-300' : ''">
        <div class="flex flex-wrap items-center gap-2"><StatusBadge :status="f.severity" /><span v-if="f.blocking" class="badge-slate">menghalangi persetujuan</span><span class="font-mono text-[10px] text-ink-400">{{ f.code }}</span><StatusBadge :status="f.status" class="ml-auto" /></div>
        <p class="mt-2 font-medium">{{ f.title }}</p><p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ f.detail }}</p><p v-if="f.note" class="mt-2 rounded bg-ink-50 px-3 py-2 text-xs italic dark:bg-ink-800">Penjelasan: {{ f.note }}</p>
        <div v-if="canResolve(f)" class="mt-3 flex flex-wrap items-center gap-2">
          <input v-model="resolveNote[f.id]" class="input flex-1" placeholder="Penjelasan & bukti verifikasi (min. 15 karakter)" /><button class="btn-accent btn-sm" @click="resolve(f, 'resolved')">Tandai selesai</button><button class="btn-secondary btn-sm" @click="resolve(f, 'waived')">Kecualikan</button></div>
      </article>
      <p v-if="!findings.length" class="card card-body text-center text-sm text-ink-400">Tidak ada temuan.</p>
    </section>

    <!-- KEUANGAN -->
    <div v-show="tab === 'money'" class="grid gap-6 lg:grid-cols-2">
      <section class="card">
        <header class="card-header"><div><h2 class="text-base font-semibold">Pengajuan dana ke Keuangan</h2><p class="text-xs text-ink-500">Keuangan hanya melihat total per akun, bukan gaji per orang.</p></div><StatusBadge v-if="funding" :status="funding.status" /></header>
        <div class="card-body text-sm">
          <template v-if="funding">
            <p class="font-mono text-xs text-ink-500">{{ funding.number }}<span v-if="funding.finance_ref"> · ref Keuangan {{ funding.finance_ref }}</span></p>
            <p v-if="funding.note" class="mt-2 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">Catatan Keuangan ({{ funding.by }}): {{ funding.note }}</p>
            <table class="data-table mt-3"><thead><tr><th>Jurnal (rincian)</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr></thead><tbody>
              <tr v-for="(v, k) in funding.breakdown.debit" :key="'d' + k"><td>{{ glLabel[k] ?? k }}</td><td class="text-right"><Money :value="v" /></td><td></td></tr>
              <tr v-for="(v, k) in funding.breakdown.credit" :key="'c' + k"><td class="pl-6">{{ glLabel[k] ?? k }}</td><td></td><td class="text-right"><Money :value="v" /></td></tr></tbody></table>
            <p v-if="funding.disbursed_at" class="mt-3 text-seal-700">Dicairkan {{ funding.disbursed_at }} · ref {{ funding.disbursement_ref ?? '—' }}</p>
            <div v-if="can.resubmit" class="mt-3"><button class="btn-primary btn-sm" @click="post('runs.resubmit-funding', run.id, { note: prompt('Catatan untuk Keuangan (opsional):') || '' })">Ajukan ulang</button></div>
          </template>
          <p v-else class="text-ink-400">Dikirim otomatis saat putaran disetujui & terkunci.</p>
        </div>
      </section>

      <section class="space-y-6">
        <div class="card">
          <header class="card-header"><div><h2 class="text-base font-semibold">Transfer bank</h2><p class="text-xs text-ink-500">Hash file dicatat & dikirim ke Keuangan sebagai pembanding.</p></div><button v-if="can.bank" class="btn-primary btn-sm" @click="post('runs.bank', run.id)">Buat file transfer</button></header>
          <div class="card-body space-y-3 text-sm">
            <div v-for="b in batches" :key="b.id" class="rounded border border-ink-200 p-3 dark:border-ink-700">
              <div class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs">{{ b.number }}</span><StatusBadge :status="b.status" /><span class="num ml-auto text-xs">{{ b.items }} transfer · <Money :value="b.total" /></span></div>
              <p class="mt-1 break-all font-mono text-[10px] text-ink-400">SHA-256 {{ b.sha256 }}</p>
              <div class="mt-2 flex flex-wrap items-center gap-2"><a v-if="can.bank" :href="route('batches.download', b.id)" class="btn-secondary btn-sm">Unduh CSV</a>
                <label v-if="can.reconcile" class="btn-secondary btn-sm cursor-pointer">Unggah hasil bank (CSV)<input type="file" accept=".csv,.txt" class="hidden" @change="reconcile(b, $event)" /></label></div>
              <ul v-if="b.recon?.mismatches?.length" class="mt-2 space-y-1 text-xs text-stamp-700"><li v-for="(m, i) in b.recon.mismatches.slice(0, 8)" :key="i">• {{ m.nip }}: {{ m.detail }}</li><li v-if="b.recon.mismatches.length > 8">… dan {{ b.recon.mismatches.length - 8 }} lainnya</li></ul>
            </div>
            <p v-if="!batches.length" class="text-ink-400">Belum ada file transfer.</p>
            <p class="text-[11px] text-ink-400">Format hasil bank: kolom <code>nip,amount,status,reference</code> (status SUCCESS / FAILED).</p>
          </div>
        </div>
        <div class="card">
          <header class="card-header"><h2 class="text-base font-semibold">Setoran pajak & BPJS</h2></header>
          <table class="data-table"><tbody><tr v-for="r in remittances" :key="r.id"><td>{{ { pph21: 'PPh Pasal 21', bpjs_kes: 'BPJS Kesehatan', bpjs_tk: 'BPJS Ketenagakerjaan' }[r.type] }}</td><td class="text-right"><Money :value="r.amount" /></td><td class="num text-xs">jatuh tempo {{ r.due }}</td><td><StatusBadge :status="r.status === 'paid' ? 'closed' : 'pending'" :label="r.status === 'paid' ? 'Terbayar' : 'Menunggu'" /></td></tr>
            <tr v-if="!remittances.length"><td class="py-6 text-center text-ink-400">Dibuat saat putaran disetujui.</td></tr></tbody></table>
        </div>
        <div v-if="can.payslips" class="card card-body"><h2 class="text-base font-semibold">Slip gaji</h2><p class="mb-3 text-sm text-ink-500">Terbit otomatis saat gaji dicairkan. Email via Gmail API dengan PDF terlindung kata sandi (tanggal lahir DDMMYYYY).</p>
          <button class="btn-primary btn-sm self-start" @click="post('runs.payslips.email', run.id)">Kirim slip via email</button></div>
      </section>
    </div>

    <!-- DETAIL ITEM -->
    <div v-if="detail" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="detail = null">
      <aside class="h-full w-full max-w-lg overflow-y-auto bg-white p-6 shadow-xl dark:bg-ink-900">
        <div class="flex items-start justify-between"><div><p class="text-xs uppercase tracking-wider text-ink-400">Rincian gaji</p><h3 class="text-lg font-semibold">{{ detail.name }}</h3></div><button class="text-2xl leading-none text-ink-400" @click="detail = null">&times;</button></div>
        <p v-if="detail.loading" class="mt-6 text-ink-400">Memuat…</p>
        <template v-else>
          <table class="data-table mt-4"><tbody>
            <tr v-for="(l, i) in detail.lines" :key="i"><td>{{ l.name }}<span v-if="!l.taxable && l.type === 'earning'" class="ml-1 text-[10px] text-ink-400">non-pajak</span></td><td class="text-right"><Money :value="l.type === 'deduction' ? -l.amount : l.amount" /></td></tr>
            <tr class="font-semibold"><td>Penghasilan bruto</td><td class="text-right"><Money :value="detail.gross_earnings" /></td></tr>
            <tr><td>BPJS Kesehatan (1%)</td><td class="text-right"><Money :value="-detail.bpjs_kes_employee" /></td></tr><tr><td>JHT (2%)</td><td class="text-right"><Money :value="-detail.bpjs_jht_employee" /></td></tr><tr><td>JP (1%)</td><td class="text-right"><Money :value="-detail.bpjs_jp_employee" /></td></tr>
            <tr><td>PPh 21 <span class="text-[10px] uppercase text-ink-400">{{ detail.pph21_method }}<template v-if="detail.ter_category"> · TER {{ detail.ter_category }} {{ detail.ter_rate }}%</template></span></td><td class="text-right"><Money :value="-detail.pph21" /></td></tr>
            <tr class="border-t-2 border-ink-900 text-base font-semibold"><td>Gaji bersih</td><td class="text-right"><Money :value="detail.net_pay" /></td></tr></tbody></table>
          <h4 class="mt-5 text-sm font-semibold">Ditanggung perusahaan</h4>
          <p class="num text-xs text-ink-600">Kes <Money :value="detail.bpjs_kes_company" /> · JHT <Money :value="detail.bpjs_jht_company" /> · JKK <Money :value="detail.bpjs_jkk_company" /> · JKM <Money :value="detail.bpjs_jkm_company" /> · JP <Money :value="detail.bpjs_jp_company" /></p>
          <h4 class="mt-5 text-sm font-semibold">Dasar perhitungan pajak</h4>
          <p class="text-xs text-ink-600">Bruto kena pajak <Money :value="detail.tax_gross" /> (termasuk premi JKK/JKM/Kes yang dibayar perusahaan).</p>
          <pre v-if="detail.tax_detail && detail.pph21_method === 'final'" class="mt-2 overflow-x-auto rounded bg-ink-50 p-2 text-[10px] dark:bg-ink-800">{{ JSON.stringify(detail.tax_detail, null, 1) }}</pre>
          <p class="mt-5 text-xs text-ink-500">Hari kerja {{ detail.days_scheduled }} · hadir {{ detail.days_present }} · alpha {{ detail.days_absent }} · cuti tak berbayar {{ detail.days_unpaid_leave }} · prorata {{ (detail.proration_factor * 100).toFixed(1) }}% · lembur {{ detail.overtime_hours }} jam</p>
          <p class="mt-3 break-all font-mono text-[10px] text-ink-400">item {{ detail.item_hash }}<br>kontrak {{ detail.contract_terms_hash }}</p>
          <a :href="route('payslips.pdf', detail.id)" target="_blank" class="btn-secondary btn-sm mt-4">Buka slip PDF</a>
        </template>
      </aside>
    </div>
  </AppLayout>
</template>
