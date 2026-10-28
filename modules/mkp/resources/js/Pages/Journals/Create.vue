<script setup>
import { todayLocal } from '@/utils'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ accounts: Array, nextNumber: String })

const form = useForm({
  journal_date: todayLocal(),
  type: 'umum',
  reference: '',
  description: '',
  lines: [
    { account_id: '', debit: 0, credit: 0, description: '' },
    { account_id: '', debit: 0, credit: 0, description: '' },
  ],
})

const totalDebit = computed(() => form.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0))
const totalCredit = computed(() => form.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0))
const difference = computed(() => Math.round((totalDebit.value - totalCredit.value) * 100) / 100)
const isBalanced = computed(() => difference.value === 0 && totalDebit.value > 0)

function addLine() {
  form.lines.push({ account_id: '', debit: 0, credit: 0, description: '' })
}
function removeLine(index) {
  if (form.lines.length > 2) form.lines.splice(index, 1)
}
function formatIDR(v) {
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(v || 0)
}
function submit() {
  form.post(route('journals.store'))
}
</script>

<template>
  <AppLayout>
    <template #title>Input Jurnal Umum</template>

    <form @submit.prevent="submit" class="space-y-6">
      <ErrorSummary :errors="form.errors" />
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">No. Jurnal</label>
            <input :value="nextNumber" disabled class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-sm text-slate-500 dark:text-slate-400" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Tanggal</label>
            <input v-model="form.journal_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Jenis Jurnal</label>
            <select v-model="form.type" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option value="umum">Jurnal Umum</option>
              <option value="penyesuaian">Jurnal Penyesuaian</option>
              <option value="penutup">Jurnal Penutup</option>
              <option value="pembalik">Jurnal Pembalik</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">No. Referensi</label>
            <input v-model="form.reference" type="text" placeholder="INV-0001" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
          </div>
        </div>
        <div class="mt-4">
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Keterangan Umum</label>
          <input v-model="form.description" type="text" placeholder="Pembayaran biaya listrik bulan Januari" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        </div>
      </div>

      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Rincian Debit / Kredit</h3>
          <button type="button" @click="addLine" class="text-xs font-medium text-emerald-600 hover:underline">+ Tambah Baris</button>
        </div>

        <div class="overflow-x-auto">
        <table class="mt-4 w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
              <th class="py-2 pr-3">Akun</th>
              <th class="py-2 pr-3">Keterangan</th>
              <th class="w-36 py-2 pr-3 text-right">Debit</th>
              <th class="w-36 py-2 pr-3 text-right">Kredit</th>
              <th class="w-10"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, i) in form.lines" :key="i" class="border-t border-slate-50 dark:border-slate-800">
              <td class="py-2 pr-3">
                <select v-model="line.account_id" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
                  <option value="" disabled>Pilih akun...</option>
                  <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
                </select>
              </td>
              <td class="py-2 pr-3"><input v-model="line.description" type="text" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></td>
              <td class="py-2 pr-3"><input v-model.number="line.debit" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-right text-sm" /></td>
              <td class="py-2 pr-3"><input v-model.number="line.credit" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-right text-sm" /></td>
              <td class="py-2 text-center">
                <button type="button" @click="removeLine(i)" class="text-slate-400 dark:text-slate-500 hover:text-rose-500">&times;</button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-slate-100 dark:border-slate-700 font-semibold">
              <td colspan="2" class="py-3 pr-3 text-right text-slate-500 dark:text-slate-400">Total</td>
              <td class="py-3 pr-3 text-right">{{ formatIDR(totalDebit) }}</td>
              <td class="py-3 pr-3 text-right">{{ formatIDR(totalCredit) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
        </div>

        <div class="mt-3 rounded-lg px-4 py-3 text-sm font-medium"
          :class="isBalanced ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600'">
          <span v-if="isBalanced">✓ Jurnal seimbang (balance). Siap disimpan.</span>
          <span v-else>⚠ Selisih debit dan kredit: {{ formatIDR(Math.abs(difference)) }}. Jurnal tidak boleh disimpan sebelum seimbang.</span>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <button type="submit" :disabled="!isBalanced || form.processing"
          class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40">
          Simpan sebagai Draft
        </button>
      </div>
    </form>
  </AppLayout>
</template>
