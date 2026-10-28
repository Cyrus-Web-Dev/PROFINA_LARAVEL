<script setup>
import { useCan } from '@/permissions'
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({
  accounts: Array, selectedAccountId: Number, startDate: String, endDate: String,
  statementEndingBalance: Number, transactions: Array, summary: Object,
})

const accountId = ref(props.selectedAccountId)
const startDate = ref(props.startDate)
const endDate = ref(props.endDate)
const statementBalance = ref(props.statementEndingBalance)
const selectedIds = ref([])
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function applyFilter() {
  router.get(route('bank-reconciliation.index'), {
    account_id: accountId.value, start_date: startDate.value, end_date: endDate.value, statement_ending_balance: statementBalance.value,
  }, { preserveState: true })
}

function toggleAll(checked) {
  selectedIds.value = checked ? props.transactions.filter(t => !t.is_reconciled).map(t => t.id) : []
}

function markSelected(reconciled) {
  if (selectedIds.value.length === 0) return
  router.post(route('bank-reconciliation.mark'), { journal_detail_ids: selectedIds.value, reconciled }, {
    onSuccess: () => { selectedIds.value = [] },
  })
}
</script>

<template>
  <AppLayout>
    <template #title>Rekonsiliasi Bank</template>

    <div class="rounded-2xl bg-white dark:bg-slate-800 p-4 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Akun Kas/Bank</label>
          <select v-model="accountId" @change="applyFilter" class="mt-1 w-56 rounded-lg border-slate-200 dark:border-slate-700 text-sm">
            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
          </select>
        </div>
        <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Dari</label><input v-model="startDate" @change="applyFilter" type="date" class="mt-1 rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Sampai</label><input v-model="endDate" @change="applyFilter" type="date" class="mt-1 rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        <div>
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Saldo Akhir Rekening Koran (Bank)</label>
          <input v-model.number="statementBalance" @change="applyFilter" type="number" class="mt-1 w-48 rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        </div>
      </div>
    </div>

    <div v-if="summary" class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700"><p class="text-xs text-slate-400 dark:text-slate-500">Saldo Menurut Buku</p><p class="mt-1 text-lg font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(summary.book_balance) }}</p></div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700"><p class="text-xs text-slate-400 dark:text-slate-500">Saldo Terekonsiliasi</p><p class="mt-1 text-lg font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(summary.reconciled_balance) }}</p></div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700"><p class="text-xs text-slate-400 dark:text-slate-500">Saldo Rekening Koran</p><p class="mt-1 text-lg font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(summary.statement_ending_balance) }}</p></div>
      <div class="rounded-2xl p-5 shadow-sm ring-1" :class="summary.is_reconciled ? 'bg-emerald-50 ring-emerald-100' : 'bg-rose-50 ring-rose-100'">
        <p class="text-xs" :class="summary.is_reconciled ? 'text-emerald-600' : 'text-rose-600'">Selisih</p>
        <p class="mt-1 text-lg font-semibold" :class="summary.is_reconciled ? 'text-emerald-700' : 'text-rose-700'">{{ formatIDR(summary.difference) }}</p>
      </div>
    </div>

    <div v-if="transactions" class="mt-6 overflow-hidden rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 p-4">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Transaksi</h3>
        <div class="flex gap-2">
          <button v-if="can('bank-reconciliation.manage')" @click="markSelected(true)" :disabled="selectedIds.length === 0" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-40">Tandai Terekonsiliasi</button>
          <button v-if="can('bank-reconciliation.manage')" @click="markSelected(false)" :disabled="selectedIds.length === 0" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 disabled:opacity-40">Batalkan Tanda</button>
        </div>
      </div>
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3"><input type="checkbox" @change="toggleAll($event.target.checked)" /></th>
            <th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">No. Jurnal</th><th class="px-4 py-3">Keterangan</th>
            <th class="px-4 py-3 text-right">Debit</th><th class="px-4 py-3 text-right">Kredit</th><th class="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in transactions" :key="t.id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3"><input type="checkbox" :value="t.id" v-model="selectedIds" :disabled="t.is_reconciled" /></td>
            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ t.journal_date }}</td>
            <td class="px-4 py-3">{{ t.journal_number }}</td>
            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ t.description || t.journal_description || '-' }}</td>
            <td class="px-4 py-3 text-right">{{ t.debit > 0 ? formatIDR(t.debit) : '-' }}</td>
            <td class="px-4 py-3 text-right">{{ t.credit > 0 ? formatIDR(t.credit) : '-' }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="t.is_reconciled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'">
                {{ t.is_reconciled ? 'Sudah' : 'Belum' }}
              </span>
            </td>
          </tr>
          <tr v-if="transactions.length === 0"><td colspan="7" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Tidak ada transaksi pada periode ini.</td></tr>
        </tbody>
      </table>
      </div>
    </div>
  </AppLayout>
</template>
