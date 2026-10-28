<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  year: { type: Number, required: true },
  months: { type: Array, required: true },
})

const reopening = ref(null) // { month, name }
const form = useForm({ year: props.year, month: 0, reason: '' })

function goYear(y) {
  router.get(route('admin.periods.index'), { year: y }, { preserveState: false })
}

function closePeriod(m) {
  const extra = m.drafts > 0 ? `\n\nPERHATIAN: masih ada ${m.drafts} jurnal draft — posting atau hapus dulu.` : ''
  if (confirm(`Tutup periode ${m.name} ${props.year}?\n\nSetelah ditutup, jurnal bertanggal di bulan ini tidak bisa dibuat, diubah, diposting, atau di-void.${extra}`)) {
    router.post(route('admin.periods.close'), { year: props.year, month: m.month }, { preserveScroll: true })
  }
}
function askReopen(m) {
  form.reset()
  form.clearErrors()
  form.year = props.year
  form.month = m.month
  reopening.value = m
}
function submitReopen() {
  form.post(route('admin.periods.reopen'), { preserveScroll: true, onSuccess: () => { reopening.value = null } })
}
</script>

<template>
  <AppLayout>
    <template #title>Periode Akuntansi</template>

    <div class="mx-auto max-w-3xl">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600" aria-label="Tahun sebelumnya" @click="goYear(year - 1)">‹</button>
          <span class="min-w-[4rem] text-center text-lg font-semibold text-slate-800 dark:text-slate-100">{{ year }}</span>
          <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600" aria-label="Tahun berikutnya" @click="goYear(year + 1)">›</button>
        </div>
        <p class="text-xs text-slate-400">Tutup bulan setelah laporannya final.</p>
      </div>

      <div class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-left text-xs uppercase text-slate-400 dark:border-slate-700">
                <th class="px-4 py-3">Bulan</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Jurnal posted</th>
                <th class="px-4 py-3 text-right">Draft</th>
                <th class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in months" :key="m.month" class="border-b border-slate-50 dark:border-slate-700/50">
                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{{ m.name }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="m.status === 'closed' ? 'bg-slate-800 text-white dark:bg-slate-600' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'">
                    {{ m.status === 'closed' ? '🔒 Ditutup' : 'Terbuka' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right tabular text-slate-500">{{ m.posted }}</td>
                <td class="px-4 py-3 text-right tabular" :class="m.drafts > 0 ? 'font-semibold text-amber-600' : 'text-slate-400'">{{ m.drafts }}</td>
                <td class="px-4 py-3 text-right">
                  <button v-if="m.status !== 'closed'" type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 dark:bg-slate-600" @click="closePeriod(m)">Tutup periode</button>
                  <button v-else type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium text-amber-700 ring-1 ring-amber-300 hover:bg-amber-50 dark:text-amber-300 dark:ring-amber-800 dark:hover:bg-amber-950" @click="askReopen(m)">Buka kembali</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4 rounded-lg bg-slate-100 p-4 text-xs leading-relaxed text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
        <p class="font-medium text-slate-600 dark:text-slate-300">Cara kerja</p>
        <ul class="mt-1 list-disc space-y-1 pl-4">
          <li>Periode ditutup hanya bila <strong>tidak ada jurnal draft / menunggu approval</strong> di bulan itu.</li>
          <li>Periode tertutup mengunci jurnal bertanggal di bulan tersebut: tidak bisa dibuat, diubah, diposting, atau di-void — termasuk dari invoice, bill, pembayaran, dan depresiasi.</li>
          <li>Membatalkan transaksi lama (invoice/bill) tetap bisa: jurnal pembaliknya dicatat di tanggal hari ini (periode yang masih terbuka).</li>
          <li>Membuka kembali periode wajib disertai alasan dan tercatat di Activity Log.</li>
        </ul>
      </div>
    </div>

    <!-- Modal buka kembali -->
    <div v-if="reopening" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" @click.self="reopening = null">
      <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-800" @submit.prevent="submitReopen">
        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">Buka kembali {{ reopening.name }} {{ year }}</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Laporan periode ini bisa berubah lagi setelah dibuka. Tulis alasannya.</p>
        <div class="mt-4"><ErrorSummary :errors="form.errors" /></div>
        <p v-if="form.errors.period" role="alert" class="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950 dark:text-rose-300">{{ form.errors.period }}</p>
        <textarea v-model="form.reason" rows="3" maxlength="300" required placeholder="mis. Koreksi invoice yang salah tanggal" class="w-full rounded-lg border-slate-200 text-sm dark:border-slate-700"></textarea>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700" @click="reopening = null">Batal</button>
          <button type="submit" :disabled="form.processing" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50">Buka kembali</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
