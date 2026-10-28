<script setup>
import { todayLocal } from '@/utils'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ vendors: Array, payableAccounts: Array, expenseAccounts: Array, taxAccounts: Array, nextNumber: String })

const form = useForm({
  vendor_id: '', bill_date: todayLocal(),
  due_date: todayLocal(), description: '',
  payable_account_id: props.payableAccounts[0]?.id ?? '',
  tax_amount: 0, tax_account_id: props.taxAccounts[0]?.id ?? '',
  items: [{ expense_account_id: '', description: '', qty: 1, price: 0 }],
})

const subtotal = computed(() => form.items.reduce((s, i) => s + (Number(i.qty) || 0) * (Number(i.price) || 0), 0))
const total = computed(() => subtotal.value + (Number(form.tax_amount) || 0))

function addItem() {
  form.items.push({ expense_account_id: '', description: '', qty: 1, price: 0 })
}
function removeItem(i) {
  if (form.items.length > 1) form.items.splice(i, 1)
}
function formatIDR(v) {
  return new Intl.NumberFormat('id-ID').format(v || 0)
}
function submit() {
  form.post(route('bills.store'))
}
</script>

<template>
  <AppLayout>
    <template #title>Buat Bill Baru</template>

    <form @submit.prevent="submit" class="space-y-6">
      <ErrorSummary :errors="form.errors" />
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">No. Bill</label><input :value="nextNumber" disabled class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-sm text-slate-500 dark:text-slate-400" /></div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Vendor</label>
            <select v-model="form.vendor_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option value="" disabled>Pilih vendor...</option>
              <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.code }} - {{ v.name }}</option>
            </select>
            <p v-if="form.errors.vendor_id" class="mt-1 text-xs text-rose-500">{{ form.errors.vendor_id }}</p>
          </div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Tanggal Bill</label><input v-model="form.bill_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Jatuh Tempo</label><input v-model="form.due_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        </div>
        <div class="mt-4">
          <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Akun Utang Usaha</label>
          <select v-model="form.payable_account_id" class="mt-1 w-full max-w-md rounded-lg border-slate-200 dark:border-slate-700 text-sm">
            <option v-for="a in payableAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
          </select>
        </div>
        <div class="mt-4"><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Keterangan</label><input v-model="form.description" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
      </div>

      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Item Pembelian / Beban</h3>
          <button type="button" @click="addItem" class="text-xs font-medium text-emerald-600 hover:underline">+ Tambah Item</button>
        </div>
        <div class="overflow-x-auto">
        <table class="mt-4 w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
              <th class="py-2 pr-3">Akun Beban/Aset</th><th class="py-2 pr-3">Keterangan</th>
              <th class="w-20 py-2 pr-3 text-right">Qty</th><th class="w-32 py-2 pr-3 text-right">Harga</th>
              <th class="w-32 py-2 pr-3 text-right">Jumlah</th><th class="w-10"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in form.items" :key="i" class="border-t border-slate-50 dark:border-slate-800">
              <td class="py-2 pr-3">
                <select v-model="item.expense_account_id" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
                  <option value="" disabled>Pilih akun...</option>
                  <option v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
                </select>
              </td>
              <td class="py-2 pr-3"><input v-model="item.description" type="text" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></td>
              <td class="py-2 pr-3"><input v-model.number="item.qty" type="number" min="0.01" step="0.01" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-right text-sm" /></td>
              <td class="py-2 pr-3"><input v-model.number="item.price" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-right text-sm" /></td>
              <td class="py-2 pr-3 text-right font-mono text-slate-600 dark:text-slate-300">{{ formatIDR(item.qty * item.price) }}</td>
              <td class="py-2 text-center"><button type="button" @click="removeItem(i)" class="text-slate-400 dark:text-slate-500 hover:text-rose-500">&times;</button></td>
            </tr>
          </tbody>
        </table>
        </div>

        <div class="mt-4 flex justify-end">
          <div class="w-64 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Subtotal</span><span>{{ formatIDR(subtotal) }}</span></div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-slate-500 dark:text-slate-400">Pajak (PPN)</span>
              <input v-model.number="form.tax_amount" type="number" min="0" step="0.01" class="w-32 rounded-lg border-slate-200 dark:border-slate-700 text-right text-sm" />
            </div>
            <div class="flex justify-between border-t border-slate-100 dark:border-slate-700 pt-2 font-semibold"><span>Total</span><span>{{ formatIDR(total) }}</span></div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40">
          Simpan & Posting Otomatis
        </button>
      </div>
    </form>
  </AppLayout>
</template>
