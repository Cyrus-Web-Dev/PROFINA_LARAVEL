<script setup>
import { ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { useCan } from '@/permissions'
import { formatIDR } from '@/utils'

const props = defineProps({
  tab: String, filters: Object, funds: Object, reports: Object, counts: Object, totals: Object,
  fundingOptions: Array, connected: Boolean, lastSync: String,
})
const can = useCan()
const canAct = () => can('procurement.decide') || can('procurement.disburse')

const STATUS = {
  submitted: ['Menunggu keputusan', 'bg-amber-100 text-amber-700'],
  approved: ['Disetujui, belum cair', 'bg-teal-100 text-teal-700'],
  revision: ['Diminta revisi', 'bg-violet-100 text-violet-700'],
  rejected: ['Ditolak', 'bg-rose-100 text-rose-600'],
  disbursed: ['Dana cair', 'bg-sky-100 text-sky-700'],
  settled: ['LPJ siap dijurnal', 'bg-indigo-100 text-indigo-700'],
  closed: ['LPJ dijurnal', 'bg-emerald-100 text-emerald-700'],
  cancelled: ['Dibatalkan Pengadaan', 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'],
}
const KIND = { pengajuan: 'Pengajuan dana', keputusan: 'Keputusan', pencairan: 'Pencairan', lpj: 'LPJ', lpj_dijurnal: 'LPJ dijurnal', catatan: 'Catatan' }
const TABS = [
  { key: 'waiting', label: 'Menunggu keputusan', n: 'waiting' },
  { key: 'decided', label: 'Sudah diputuskan' },
  { key: 'cash', label: 'Pencairan', n: 'cash' },
  { key: 'settle', label: 'LPJ', n: 'settle' },
  { key: 'all', label: 'Semua' },
  { key: 'reports', label: 'Laporan Pengadaan', n: 'unread' },
]
const STAMP = { approved: ['border-emerald-600 text-emerald-700', 'DISETUJUI'], disbursed: ['border-sky-600 text-sky-700', 'DANA CAIR'], closed: ['border-emerald-600 text-emerald-700', 'SELESAI'],
  settled: ['border-indigo-600 text-indigo-700', 'LPJ MASUK'], rejected: ['border-rose-600 text-rose-600', 'DITOLAK'], revision: ['border-violet-600 text-violet-700', 'REVISI'] }

const search = ref(props.filters.search ?? '')
let t = null
watch(search, (v) => { clearTimeout(t); t = setTimeout(() => router.get(route('procurement.index'), { tab: props.tab, ...(v ? { search: v } : {}) }, { preserveState: true, replace: true }), 300) })
const go = (tab) => router.get(route('procurement.index'), { tab }, { preserveState: true })

const syncing = ref(false)
function syncNow() { syncing.value = true; router.post(route('procurement.sync'), {}, { preserveScroll: true, onFinish: () => { syncing.value = false } }) }
const markRead = (r) => router.post(route('procurement.reports.read', r.id), {}, { preserveScroll: true })
const readAll = () => router.post(route('procurement.reports.read-all'), {}, { preserveScroll: true })

const showNote = ref(false)
const noteForm = useForm({ funding_id: '', subject: '', body: '' })
function submitNote() { noteForm.post(route('procurement.note'), { preserveScroll: true, onSuccess: () => { showNote.value = false; noteForm.reset() } }) }
</script>

<template>
  <AppLayout>
    <template #title>Dana Pengadaan</template>
    <template #actions>
      <button v-if="canAct()" :disabled="syncing" class="rounded-lg bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700 hover:bg-slate-50 disabled:opacity-50" @click="syncNow">
        {{ syncing ? 'Memuat…' : 'Muat dari Pengadaan' }}
      </button>
    </template>

    <div v-if="!connected" class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
      Integrasi PROCURA belum diatur. Isi <code class="font-mono">PROCURA_URL</code>, <code class="font-mono">PROCURA_TOKEN</code>, dan <code class="font-mono">PROCURA_WEBHOOK_SECRET</code> di <code class="font-mono">.env</code>, lalu <code class="font-mono">php artisan config:clear</code>.
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <p class="text-xs text-slate-400 dark:text-slate-500">Diajukan, menunggu keputusan</p>
        <p class="mt-1 font-mono text-xl font-semibold text-amber-600">{{ formatIDR(totals.waiting) }}</p>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <p class="text-xs text-slate-400 dark:text-slate-500">Disetujui, belum dicairkan</p>
        <p class="mt-1 font-mono text-xl font-semibold text-teal-600">{{ formatIDR(totals.approved) }}</p>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <p class="text-xs text-slate-400 dark:text-slate-500">Uang muka di tangan Pengadaan</p>
        <p class="mt-1 font-mono text-xl font-semibold text-sky-600">{{ formatIDR(totals.outstanding) }}</p>
      </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap gap-2">
        <button v-for="x in TABS" :key="x.key" class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium"
          :class="tab === x.key ? 'bg-slate-800 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 ring-1 ring-slate-200 dark:ring-slate-700'" @click="go(x.key)">
          {{ x.label }}<span v-if="x.n && counts[x.n]" class="rounded-full bg-orange-500 px-1.5 text-[10px] font-bold leading-4 text-white">{{ counts[x.n] }}</span>
        </button>
      </div>
      <input v-if="tab !== 'reports'" v-model="search" type="search" placeholder="Cari nomor / judul…" aria-label="Cari"
        class="w-64 rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      <div v-else class="flex gap-2">
        <button v-if="counts.unread" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-500 ring-1 ring-slate-200 hover:bg-slate-50" @click="readAll">Tandai semua dibaca</button>
        <button v-if="canAct()" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700" @click="showNote = true">Tulis laporan ke Pengadaan</button>
      </div>
    </div>

    <!-- Daftar pengajuan -->
    <template v-if="tab !== 'reports'">
      <div v-if="funds.data.length" class="grid gap-4 md:grid-cols-2">
        <Link v-for="f in funds.data" :key="f.id" :href="route('procurement.show', f.id)"
          class="group relative block overflow-hidden rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700 transition hover:ring-emerald-300">
          <span v-if="STAMP[f.status]" class="absolute right-4 top-4 -rotate-6 rounded-md border-2 px-2.5 py-0.5 text-xs font-extrabold tracking-wide" :class="STAMP[f.status][0]">{{ STAMP[f.status][1] }}</span>
          <p class="font-mono text-xs text-slate-400">{{ f.procura_number }}<span v-if="f.finance_ref"> · {{ f.finance_ref }}</span></p>
          <h3 class="mt-1 max-w-[62%] font-medium leading-snug text-slate-800 dark:text-slate-100 group-hover:text-emerald-600">{{ f.request_title || f.request_number }}</h3>
          <p class="mt-3 font-mono text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(f.approved_amount ?? f.amount) }}</p>
          <p v-if="f.approved_amount && f.approved_amount < f.amount" class="font-mono text-xs text-slate-400">diajukan {{ formatIDR(f.amount) }}</p>
          <p class="mt-2 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full px-2 py-0.5 font-medium" :class="STATUS[f.status]?.[1]">{{ STATUS[f.status]?.[0] ?? f.status }}</span>
            <span v-if="f.decided_by_name" class="text-slate-400">oleh {{ f.decided_by_name }} · {{ f.decided_at }}</span>
            <span v-else-if="f.submitted_at" class="text-slate-400">diajukan {{ f.submitted_at }}</span>
          </p>
          <p v-if="f.decision_note" class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">“{{ f.decision_note }}”</p>
        </Link>
      </div>
      <div v-else class="rounded-2xl bg-white dark:bg-slate-800 px-6 py-14 text-center shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <p class="font-medium text-slate-700 dark:text-slate-200">Tidak ada pengajuan di tab ini</p>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-400">Pengajuan dari Pengadaan masuk otomatis lewat webhook. {{ lastSync ? 'Terakhir disinkronkan ' + lastSync + '.' : '' }}</p>
      </div>
      <Pagination :links="funds.links" />
    </template>

    <!-- Laporan -->
    <template v-else>
      <div v-if="reports.data.length" class="space-y-3">
        <article v-for="r in reports.data" :key="r.id" class="relative rounded-2xl bg-white dark:bg-slate-800 p-4 pl-5 shadow-sm ring-1"
          :class="r.direction === 'masuk' && !r.read ? 'ring-orange-300' : 'ring-slate-100 dark:ring-slate-700'">
          <span class="absolute inset-y-3 left-0 w-1 rounded-r" :class="r.direction === 'masuk' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-md px-2 py-0.5 font-medium" :class="r.direction === 'masuk' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ r.direction === 'masuk' ? 'Dari Pengadaan' : 'Dari Keuangan' }}</span>
                <span class="text-slate-400">{{ KIND[r.kind] ?? r.kind }}</span>
                <span v-if="r.direction === 'masuk' && !r.read" class="rounded-md bg-orange-100 px-2 py-0.5 font-medium text-orange-700">Baru</span>
              </p>
              <h3 class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ r.subject }}</h3>
            </div>
            <span class="font-mono text-xs text-slate-400">{{ r.created_at }}</span>
          </div>
          <p v-if="r.body" class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ r.body }}</p>
          <div class="mt-3 flex items-center justify-between text-xs">
            <span class="text-slate-400"><Link v-if="r.funding_id" :href="route('procurement.show', r.funding_id)" class="font-mono text-emerald-600 hover:underline">{{ r.funding_number }}</Link><template v-if="r.author"> · {{ r.author }}</template></span>
            <button v-if="r.direction === 'masuk' && !r.read" class="rounded-lg px-2.5 py-1 font-medium text-emerald-700 hover:bg-emerald-50" @click="markRead(r)">Tandai dibaca</button>
          </div>
        </article>
      </div>
      <div v-else class="rounded-2xl bg-white dark:bg-slate-800 px-6 py-14 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">Belum ada laporan.</div>
      <Pagination :links="reports.links" />
    </template>

    <div v-if="showNote" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Laporan ke Pengadaan</h3>
        <form class="mt-4 space-y-4" @submit.prevent="submitNote">
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Terkait pengajuan (opsional)</label>
            <select v-model="noteForm.funding_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option value="">— tidak terkait —</option>
              <option v-for="o in fundingOptions" :key="o.id" :value="o.id">{{ o.procura_number }} · {{ o.request_title }}</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">Laporan yang terkait pengajuan ikut dikirim ke Pengadaan lewat API.</p>
          </div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Perihal</label><input v-model="noteForm.subject" required class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /><p v-if="noteForm.errors.subject" class="mt-1 text-xs text-rose-500">{{ noteForm.errors.subject }}</p></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Isi</label><textarea v-model="noteForm.body" rows="4" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm"></textarea></div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" @click="showNote = false">Batal</button>
            <button type="submit" :disabled="noteForm.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Kirim</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
