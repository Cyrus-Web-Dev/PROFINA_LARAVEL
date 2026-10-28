<script setup>
import { useCan } from '@/permissions'
import { todayLocal } from '@/utils'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router, Link } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ summary: Array, accounts: Array, asOfDate: String })
const showModal = ref(false)
const formatIDR = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v || 0)

const form = useForm({
  transfer_date: todayLocal(),
  from_account_id: props.accounts[0]?.id ?? '',
  to_account_id: props.accounts[1]?.id ?? '',
  amount: 0,
  note: '',
})

function submit() {
  form.post(route('cashbank.transfer'), { onSuccess: () => { showModal.value = false; form.reset('amount', 'note') } })
}
</script>

<template>
  <AppLayout>
    <template #title>Kas & Bank</template>
    <template #actions>
      <button v-if="can('cashbank.manage')" @click="showModal = true" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">
        ⇄ Transfer Kas/Bank
      </button>
    </template>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="acc in summary" :key="acc.id" class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <p class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ acc.code }}</p>
        <p class="mt-1 text-sm font-medium text-slate-600 dark:text-slate-300">{{ acc.name }}</p>
        <p class="mt-2 text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(acc.balance) }}</p>
        <Link :href="route('ledger.index', { account_id: acc.id })" class="mt-3 inline-block text-xs font-medium text-emerald-600 hover:underline">Lihat mutasi →</Link>
      </div>
      <div v-if="summary.length === 0" class="col-span-full rounded-2xl bg-white dark:bg-slate-800 p-8 text-center text-slate-400 dark:text-slate-500 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        Belum ada akun yang ditandai sebagai Kas/Bank. Tandai di halaman Daftar Akun.
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Transfer Kas/Bank</h3>
        <form @submit.prevent="submit" class="mt-4 space-y-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Tanggal</label><input v-model="form.transfer_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Dari Akun</label>
            <select v-model="form.from_account_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Ke Akun</label>
            <select v-model="form.to_account_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
            </select>
            <p v-if="form.errors.to_account_id" class="mt-1 text-xs text-rose-500">{{ form.errors.to_account_id }}</p>
          </div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Jumlah</label><input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Keterangan</label><input v-model="form.note" type="text" placeholder="Setor tunai ke bank" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
