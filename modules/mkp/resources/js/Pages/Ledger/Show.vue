<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ accounts: Array, selectedAccountId: Number, startDate: String, endDate: String, ledger: Object })

const accountId = ref(props.selectedAccountId)
const startDate = ref(props.startDate)
const endDate = ref(props.endDate)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function applyFilter() {
  router.get(route('ledger.index'), { account_id: accountId.value, start_date: startDate.value, end_date: endDate.value }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Buku Besar</template>

    <div class="rounded-2xl bg-white dark:bg-slate-800 p-4 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Akun</label>
          <select v-model="accountId" @change="applyFilter" class="mt-1 w-64 rounded-lg border-slate-200 dark:border-slate-700 text-sm">
            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Dari Tanggal</label>
          <input v-model="startDate" @change="applyFilter" type="date" class="mt-1 rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Sampai Tanggal</label>
          <input v-model="endDate" @change="applyFilter" type="date" class="mt-1 rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        </div>
      </div>
    </div>

    <div v-if="ledger" class="mt-6 rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ ledger.account.code }} - {{ ledger.account.name }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400">Saldo Awal: <span class="font-medium text-slate-800 dark:text-slate-100">{{ formatIDR(ledger.opening_balance) }}</span></p>
      </div>

      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="py-2">Tanggal</th><th class="py-2">No. Jurnal</th><th class="py-2">Keterangan</th>
            <th class="py-2 text-right">Debit</th><th class="py-2 text-right">Kredit</th><th class="py-2 text-right">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in ledger.rows" :key="i" class="border-b border-slate-50 dark:border-slate-800">
            <td class="py-2 text-slate-500 dark:text-slate-400">{{ r.date }}</td>
            <td class="py-2">{{ r.journal_number }}</td>
            <td class="py-2 text-slate-500 dark:text-slate-400">{{ r.description || '-' }}</td>
            <td class="py-2 text-right">{{ r.debit > 0 ? formatIDR(r.debit) : '-' }}</td>
            <td class="py-2 text-right">{{ r.credit > 0 ? formatIDR(r.credit) : '-' }}</td>
            <td class="py-2 text-right font-medium">{{ formatIDR(r.balance) }}</td>
          </tr>
          <tr v-if="ledger.rows.length === 0"><td colspan="6" class="py-8 text-center text-slate-400 dark:text-slate-500">Tidak ada mutasi pada periode ini.</td></tr>
        </tbody>
        <tfoot>
          <tr class="border-t-2 border-slate-100 dark:border-slate-700 font-semibold">
            <td colspan="5" class="py-3 text-right text-slate-500 dark:text-slate-400">Saldo Akhir</td>
            <td class="py-3 text-right">{{ formatIDR(ledger.closing_balance) }}</td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>
  </AppLayout>
</template>
