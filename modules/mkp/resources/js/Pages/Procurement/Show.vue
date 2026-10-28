<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AttachmentPanel from '@/Components/AttachmentPanel.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { useCan } from '@/permissions'
import { formatIDR, todayLocal } from '@/utils'

const props = defineProps({
  funding: Object, reports: Array, cashAccounts: Array, expenseAccounts: Array, suggestedAccountId: Number, capitalCategory: Boolean, threshold: Number,
})
const can = useCan()
const f = computed(() => props.funding)
const modal = ref(null)
const close = () => { modal.value = null }
const done = { preserveScroll: true, onSuccess: close }

const STATUS = {
  submitted: ['Menunggu keputusan', 'bg-amber-100 text-amber-700'], approved: ['Disetujui, belum cair', 'bg-teal-100 text-teal-700'],
  revision: ['Diminta revisi', 'bg-violet-100 text-violet-700'], rejected: ['Ditolak', 'bg-rose-100 text-rose-600'], disbursed: ['Dana cair', 'bg-sky-100 text-sky-700'],
  settled: ['LPJ siap dijurnal', 'bg-indigo-100 text-indigo-700'], closed: ['LPJ dijurnal', 'bg-emerald-100 text-emerald-700'], cancelled: ['Dibatalkan Pengadaan', 'bg-slate-100 text-slate-600'],
}
const STAMP = { approved: ['border-emerald-600 text-emerald-700', 'DISETUJUI'], disbursed: ['border-sky-600 text-sky-700', 'DANA CAIR'], closed: ['border-emerald-600 text-emerald-700', 'SELESAI'],
  settled: ['border-indigo-600 text-indigo-700', 'LPJ MASUK'], rejected: ['border-rose-600 text-rose-600', 'DITOLAK'], revision: ['border-violet-600 text-violet-700', 'REVISI'] }
const KIND = { pengajuan: 'Pengajuan dana', keputusan: 'Keputusan', pencairan: 'Pencairan', lpj: 'LPJ', lpj_dijurnal: 'LPJ dijurnal', catatan: 'Catatan' }
const CAT = { elektronik: 'Elektronik', non_elektronik: 'Non Elektronik', perabotan: 'Perabotan', kendaraan: 'Kendaraan', gedung: 'Gedung & Renovasi', jasa: 'Jasa', habis_pakai: 'Habis Pakai' }

// --- keputusan ---
const dForm = useForm({ decision: 'approve', approved_amount: props.funding.amount, note: '' })
const needsManager = computed(() => dForm.decision === 'approve' && Number(dForm.approved_amount) >= props.threshold && !can('journal.approve'))
function openDecide() { dForm.reset(); dForm.approved_amount = f.value.amount; dForm.clearErrors(); modal.value = 'decide' }
const submitDecision = () => dForm.post(route('procurement.decide', f.value.id), done)

// --- pencairan ---
const cForm = useForm({ cash_account_id: props.cashAccounts[0]?.id ?? '', amount: 0, date: todayLocal(), method: 'transfer', reference: '' })
const selectedCash = computed(() => props.cashAccounts.find((a) => a.id === Number(cForm.cash_account_id)))
function openCash() { cForm.amount = f.value.approved_amount; cForm.date = todayLocal(); cForm.clearErrors(); modal.value = 'cash' }
const submitCash = () => cForm.post(route('procurement.disburse', f.value.id), done)

// --- LPJ ---
const lForm = useForm({ account_id: props.suggestedAccountId ?? '', return_account_id: '', date: todayLocal(), note: '' })
function openSettle() { lForm.return_account_id = f.value.cash_account?.id ?? props.cashAccounts[0]?.id ?? ''; lForm.date = todayLocal(); lForm.clearErrors(); modal.value = 'settle' }
const submitSettle = () => lForm.post(route('procurement.settle', f.value.id), done)

const refresh = () => router.post(route('procurement.refresh', f.value.id), {}, { preserveScroll: true })

const timeline = computed(() => [
  { label: 'Diajukan Pengadaan', done: !!f.value.submitted_at, at: f.value.submitted_at },
  { label: 'Keputusan Keuangan', done: !!f.value.decided_at, at: f.value.decided_at },
  { label: 'Dana dicairkan', done: !!f.value.disbursed_at, at: f.value.disbursed_at },
  { label: 'LPJ diserahkan Pengadaan', done: !!f.value.settled_at, at: f.value.settled_at },
  { label: 'LPJ dijurnal', done: f.value.status === 'closed', at: f.value.settlement_journal?.journal_number },
])
const input = 'mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm'
const lbl = 'text-xs font-medium text-slate-500 dark:text-slate-400'
</script>

<template>
  <AppLayout>
    <template #title>Dana Pengadaan {{ funding.procura_number }}</template>
    <template #actions>
      <button v-if="can('procurement.decide') || can('procurement.disburse')" class="rounded-lg bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50" @click="refresh">Muat ulang dari Pengadaan</button>
    </template>

    <Link :href="route('procurement.index')" class="text-xs text-slate-400 hover:underline">← Dana Pengadaan</Link>

    <div v-if="funding.last_error" class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700 ring-1 ring-rose-200">{{ funding.last_error }}</div>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="flex items-center gap-2 text-xs"><span class="font-mono text-slate-400">{{ funding.procura_number }}<span v-if="funding.finance_ref"> · {{ funding.finance_ref }}</span></span>
          <span class="rounded-full px-2 py-0.5 font-medium" :class="STATUS[funding.status]?.[1]">{{ STATUS[funding.status]?.[0] ?? funding.status }}</span></p>
        <h2 class="mt-1 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ funding.request_title || funding.request_number }}</h2>
        <p class="mt-1 text-sm text-slate-400">Permintaan Pengadaan <span class="font-mono">{{ funding.request_number }}</span> · {{ CAT[funding.category] ?? funding.category }}</p>
      </div>
      <span v-if="STAMP[funding.status]" class="-rotate-6 rounded-md border-[3px] px-4 py-1.5 text-lg font-extrabold tracking-wide" :class="STAMP[funding.status][0]">{{ STAMP[funding.status][1] }}</span>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <section class="rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
          <div class="grid divide-y divide-slate-100 dark:divide-slate-700 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="p-5"><p :class="lbl">Diajukan</p><p class="mt-1 font-mono text-xl font-semibold">{{ formatIDR(funding.amount) }}</p></div>
            <div class="p-5"><p :class="lbl">Disetujui</p><p class="mt-1 font-mono text-xl font-semibold text-teal-600">{{ funding.approved_amount ? formatIDR(funding.approved_amount) : '—' }}</p></div>
            <div class="p-5"><p :class="lbl">Dicairkan</p><p class="mt-1 font-mono text-xl font-semibold text-sky-600">{{ funding.disbursed_amount ? formatIDR(funding.disbursed_amount) : '—' }}</p></div>
          </div>
          <div class="border-t border-slate-100 dark:border-slate-700 p-5 text-sm">
            <p :class="lbl">Tujuan penggunaan dana</p><p class="mt-0.5">{{ funding.purpose || '—' }}</p>
          </div>
        </section>

        <section v-if="funding.decided_at" class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Keputusan Keuangan</h3>
          <p class="mt-2 text-sm"><strong>{{ funding.decided_by_name }}</strong> <span class="text-slate-400">· {{ funding.decided_at }}</span></p>
          <p v-if="funding.decision_note" class="mt-2 rounded-lg bg-slate-50 dark:bg-slate-900 p-3 text-sm">“{{ funding.decision_note }}”</p>
        </section>

        <section v-if="funding.disbursed_at" class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Pencairan & pembukuan</h3>
          <dl class="mt-3 grid grid-cols-2 gap-4 text-sm">
            <div><dt :class="lbl">Tanggal</dt><dd>{{ funding.disbursed_at }}</dd></div>
            <div><dt :class="lbl">Sumber dana</dt><dd>{{ funding.cash_account ? funding.cash_account.code + ' - ' + funding.cash_account.name : '—' }}</dd></div>
            <div><dt :class="lbl">Metode</dt><dd>{{ funding.disbursement_method }}<span v-if="funding.disbursement_ref" class="text-slate-400"> · {{ funding.disbursement_ref }}</span></dd></div>
            <div v-if="funding.disbursement_journal"><dt :class="lbl">Jurnal pencairan</dt><dd><Link :href="route('journals.show', funding.disbursement_journal.id)" class="font-mono text-emerald-600 hover:underline">{{ funding.disbursement_journal.journal_number }}</Link></dd></div>
          </dl>
          <div v-if="funding.settled_at" class="mt-4 border-t border-slate-100 dark:border-slate-700 pt-4">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">LPJ dari Pengadaan</h4>
            <dl class="mt-2 grid grid-cols-3 gap-4 text-sm font-mono">
              <div><dt class="font-sans" :class="lbl">Dana cair</dt><dd>{{ formatIDR(funding.disbursed_amount) }}</dd></div>
              <div><dt class="font-sans" :class="lbl">Terpakai</dt><dd>{{ formatIDR(funding.actual_spent) }}</dd></div>
              <div><dt class="font-sans" :class="lbl">Dikembalikan</dt><dd>{{ formatIDR(funding.returned_amount) }}</dd></div>
            </dl>
            <p v-if="funding.settlement_journal" class="mt-3 text-sm">Dijurnal ke <strong>{{ funding.settlement_account?.code }} {{ funding.settlement_account?.name }}</strong> ·
              <Link :href="route('journals.show', funding.settlement_journal.id)" class="font-mono text-emerald-600 hover:underline">{{ funding.settlement_journal.journal_number }}</Link></p>
          </div>
        </section>

        <section class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
          <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Bukti & dokumen</h3>
          <AttachmentPanel type="procurement" :record-id="funding.id" :attachments="funding.attachments" :can-upload="can('procurement.decide') || can('procurement.disburse')" />
        </section>
      </div>

      <aside class="space-y-6">
        <section class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
          <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Tindakan</h3>
          <div class="space-y-2">
            <template v-if="funding.status === 'submitted'">
              <button v-if="can('procurement.decide')" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700" @click="openDecide">Beri keputusan</button>
              <p v-else class="text-sm text-slate-400">Menunggu keputusan dari pemegang hak “Putuskan pengajuan dana”.</p>
            </template>
            <template v-if="funding.status === 'approved'">
              <button v-if="can('procurement.disburse')" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700" @click="openCash">Cairkan dana</button>
              <p v-else class="text-sm text-slate-400">Disetujui. Menunggu pencairan oleh bagian kas.</p>
            </template>
            <template v-if="funding.status === 'settled'">
              <button v-if="can('procurement.disburse')" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700" @click="openSettle">Terima LPJ & buat jurnal</button>
              <p v-else class="text-sm text-slate-400">LPJ sudah masuk, menunggu dijurnal.</p>
            </template>
            <p v-if="funding.status === 'disbursed'" class="text-sm text-slate-400">Dana ada di Pengadaan. LPJ akan muncul di sini setelah mereka menyerahkannya.</p>
            <p v-if="funding.status === 'revision'" class="text-sm text-slate-400">Menunggu Pengadaan merevisi dan mengajukan ulang.</p>
            <p v-if="['rejected', 'closed', 'cancelled'].includes(funding.status)" class="text-sm text-slate-400">Tidak ada tindakan lanjutan.</p>
          </div>
        </section>

        <section class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
          <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Jejak dokumen</h3>
          <ol class="space-y-3">
            <li v-for="s in timeline" :key="s.label" class="flex items-center gap-3 text-sm">
              <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full border-2" :class="s.done ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 dark:border-slate-600'">
                <svg v-if="s.done" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" /></svg></span>
              <span :class="s.done ? '' : 'text-slate-400'">{{ s.label }}</span><span v-if="s.at" class="ml-auto font-mono text-xs text-slate-400">{{ s.at }}</span>
            </li>
          </ol>
          <p v-if="funding.synced_at" class="mt-3 text-xs text-slate-400">Disinkronkan {{ funding.synced_at }}</p>
        </section>

        <section v-if="reports.length" class="rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
          <h3 class="border-b border-slate-100 dark:border-slate-700 px-5 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Laporan terkait</h3>
          <ul class="divide-y divide-slate-100 dark:divide-slate-700">
            <li v-for="r in reports" :key="r.id" class="px-5 py-3 text-sm">
              <p>{{ r.subject }}</p>
              <p class="text-xs text-slate-400">{{ r.created_at }} · {{ r.direction === 'masuk' ? 'dari Pengadaan' : 'dari Keuangan' }} · {{ KIND[r.kind] ?? r.kind }}</p>
            </li>
          </ul>
        </section>
      </aside>
    </div>

    <!-- Keputusan -->
    <div v-if="modal === 'decide'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Keputusan Keuangan</h3>
        <form class="mt-4 space-y-4" @submit.prevent="submitDecision">
          <ErrorSummary :errors="dForm.errors" />
          <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Keputusan">
            <label v-for="o in [['approve', 'Setujui'], ['revision', 'Minta revisi'], ['reject', 'Tolak']]" :key="o[0]" class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-medium"
              :class="dForm.decision === o[0] ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 dark:border-slate-600'">
              <input v-model="dForm.decision" type="radio" :value="o[0]" class="sr-only" />{{ o[1] }}</label>
          </div>
          <div v-if="dForm.decision === 'approve'">
            <label :class="lbl">Nominal disetujui (maks. {{ formatIDR(funding.amount) }})</label>
            <input v-model.number="dForm.approved_amount" type="number" min="1" step="any" :max="funding.amount" :class="input" />
            <p v-if="needsManager" class="mt-1 text-xs text-amber-600">Nominal ≥ {{ formatIDR(threshold) }} hanya bisa disetujui oleh Manager (hak “Setujui jurnal bernilai besar”).</p>
          </div>
          <div>
            <label :class="lbl">{{ dForm.decision === 'approve' ? 'Catatan (opsional)' : 'Alasan / catatan (wajib)' }}</label>
            <textarea v-model="dForm.note" rows="3" :required="dForm.decision !== 'approve'" :class="input"></textarea>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" @click="close">Batal</button>
            <button type="submit" :disabled="dForm.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Catat keputusan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Pencairan -->
    <div v-if="modal === 'cash'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Cairkan dana ke Pengadaan</h3>
        <form class="mt-4 space-y-4" @submit.prevent="submitCash">
          <ErrorSummary :errors="cForm.errors" />
          <div>
            <label :class="lbl">Sumber dana (Kas / Bank)</label>
            <select v-model="cForm.cash_account_id" required :class="input"><option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option></select>
            <p v-if="selectedCash" class="mt-1 text-xs" :class="selectedCash.balance < cForm.amount ? 'text-rose-600' : 'text-slate-400'">Saldo saat ini {{ formatIDR(selectedCash.balance) }}<span v-if="selectedCash.balance < cForm.amount"> — tidak cukup</span></p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label :class="lbl">Nominal (maks. {{ formatIDR(funding.approved_amount) }})</label><input v-model.number="cForm.amount" type="number" min="1" step="any" :max="funding.approved_amount" required :class="input" /></div>
            <div><label :class="lbl">Tanggal</label><input v-model="cForm.date" type="date" required :class="input" /></div>
            <div><label :class="lbl">Metode</label><select v-model="cForm.method" :class="input"><option value="transfer">Transfer</option><option value="tunai">Tunai</option><option value="cek">Cek</option></select></div>
            <div><label :class="lbl">No. referensi</label><input v-model="cForm.reference" :class="input" /></div>
          </div>
          <p class="rounded-lg bg-slate-50 dark:bg-slate-900 p-3 text-xs text-slate-500">Jurnal otomatis: <strong>Dr Uang Muka Pengadaan</strong> / <strong>Kr Kas-Bank</strong>. Bila PROCURA tidak bisa dihubungi, pencairan dibatalkan seluruhnya (tidak ada jurnal setengah jadi).</p>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" @click="close">Batal</button>
            <button type="submit" :disabled="cForm.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Cairkan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Terima LPJ -->
    <div v-if="modal === 'settle'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Terima LPJ & buat jurnal</h3>
        <form class="mt-4 space-y-4" @submit.prevent="submitSettle">
          <ErrorSummary :errors="lForm.errors" />
          <dl class="grid grid-cols-3 gap-3 rounded-lg bg-slate-50 dark:bg-slate-900 p-3 font-mono text-sm">
            <div><dt class="font-sans text-xs text-slate-400">Dana cair</dt><dd>{{ formatIDR(funding.disbursed_amount) }}</dd></div>
            <div><dt class="font-sans text-xs text-slate-400">Terpakai</dt><dd>{{ formatIDR(funding.actual_spent) }}</dd></div>
            <div><dt class="font-sans text-xs text-slate-400">Kembali</dt><dd>{{ formatIDR(funding.returned_amount) }}</dd></div>
          </dl>
          <div v-if="funding.actual_spent > 0">
            <label :class="lbl">Dibukukan ke akun (aset / beban)</label>
            <select v-model="lForm.account_id" required :class="input"><option value="" disabled>Pilih akun…</option><option v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option></select>
            <p class="mt-1 text-xs text-slate-400">Saran akun mengikuti kategori ({{ CAT[funding.category] ?? '-' }}); Anda yang menentukan apakah dikapitalisasi atau dibebankan.</p>
            <p v-if="capitalCategory" class="mt-1 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">Bila ini aset yang disusutkan, daftarkan juga di menu <Link :href="route('fixed-assets.create')" class="font-medium underline">Aset Tetap</Link> setelah LPJ dijurnal.</p>
          </div>
          <div v-if="funding.returned_amount > 0">
            <label :class="lbl">Sisa dana {{ formatIDR(funding.returned_amount) }} dikembalikan ke</label>
            <select v-model="lForm.return_account_id" required :class="input"><option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option></select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label :class="lbl">Tanggal jurnal</label><input v-model="lForm.date" type="date" required :class="input" /></div>
            <div><label :class="lbl">Catatan</label><input v-model="lForm.note" :class="input" /></div>
          </div>
          <p class="rounded-lg bg-slate-50 dark:bg-slate-900 p-3 text-xs text-slate-500">Jurnal otomatis: <strong>Dr Aset/Beban</strong> + <strong>Dr Kas-Bank</strong> (sisa) / <strong>Kr Uang Muka Pengadaan</strong>.</p>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" @click="close">Batal</button>
            <button type="submit" :disabled="lForm.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Jurnalkan LPJ</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
