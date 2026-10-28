<script setup>
import { useCan } from '@/permissions'
import { todayLocal } from '@/utils'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AttachmentPanel from '@/Components/AttachmentPanel.vue'
import { useForm, Link, router } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({
  attachments: { type: Array, default: () => [] }, invoice: Object, cashBankAccounts: Array })
const showPayModal = ref(false)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)
const statusBadge = { draft: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300', unpaid: 'bg-amber-100 text-amber-700', partial: 'bg-blue-100 text-blue-700', paid: 'bg-emerald-100 text-emerald-700', void: 'bg-rose-100 text-rose-600' }

const form = useForm({
  payment_date: todayLocal(),
  amount: props.invoice.total - props.invoice.paid_amount,
  cash_account_id: props.cashBankAccounts[0]?.id ?? '',
  note: '',
})

// Isi ulang nominal = sisa tagihan TERBARU setiap modal dibuka (nilai awal form bisa sudah usang setelah pembayaran sebelumnya)
function openPay() {
  form.amount = Math.round((Number(props.invoice.total) - Number(props.invoice.paid_amount)) * 100) / 100
  form.clearErrors()
  showPayModal.value = true
}
function submitPayment() {
  form.post(route('invoices.pay', props.invoice.id), { onSuccess: () => { showPayModal.value = false } })
}
function voidInvoice() {
  if (confirm('Batalkan invoice ini? Jurnal pembalik akan otomatis dibuat.')) {
    router.post(route('invoices.void', props.invoice.id))
  }
}
</script>

<template>
  <AppLayout>
    <template #title>Invoice {{ invoice.invoice_number }}</template>
    <template #actions>
      <a :href="route('invoices.pdf', invoice.id)" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">
        ⬇ Unduh PDF
      </a>
      <button v-if="can('ar.manage') && (invoice.status !== 'paid' && invoice.status !== 'void')" @click="openPay" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
        Catat Pembayaran
      </button>
      <button v-if="can('ar.manage') && (invoice.status === 'unpaid')" @click="voidInvoice" class="rounded-lg bg-rose-50 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-100">
        Batalkan Invoice
      </button>
    </template>

    <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs text-slate-400 dark:text-slate-500">Pelanggan</p>
          <p class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ invoice.customer.name }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-medium" :class="statusBadge[invoice.status]">{{ invoice.status }}</span>
      </div>
      <div class="mt-4 grid grid-cols-4 gap-4 text-sm">
        <div><p class="text-xs text-slate-400 dark:text-slate-500">Tanggal</p><p>{{ invoice.invoice_date }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-slate-500">Jatuh Tempo</p><p>{{ invoice.due_date }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-slate-500">Total</p><p class="font-semibold">{{ formatIDR(invoice.total) }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-slate-500">Sisa</p><p class="font-semibold text-amber-600">{{ formatIDR(invoice.total - invoice.paid_amount) }}</p></div>
      </div>

      <div class="overflow-x-auto">
      <table class="mt-6 w-full text-sm">
        <thead><tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
          <th class="py-2">Deskripsi</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Harga</th><th class="py-2 text-right">Jumlah</th>
        </tr></thead>
        <tbody>
          <tr v-for="item in invoice.items" :key="item.id" class="border-b border-slate-50 dark:border-slate-800">
            <td class="py-2">{{ item.description }}</td>
            <td class="py-2 text-right">{{ item.qty }}</td>
            <td class="py-2 text-right">{{ formatIDR(item.price) }}</td>
            <td class="py-2 text-right">{{ formatIDR(item.amount) }}</td>
          </tr>
        </tbody>
      </table>
      </div>

      <div v-if="invoice.payments.length" class="mt-6">
        <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Riwayat Pembayaran</h4>
        <div v-for="p in invoice.payments" :key="p.id" class="flex justify-between border-b border-slate-50 dark:border-slate-800 py-2 text-sm">
          <span class="text-slate-500 dark:text-slate-400">{{ p.payment_date }} · {{ p.cash_account.name }}</span>
          <span class="font-medium">{{ formatIDR(p.amount) }}</span>
        </div>
      </div>

      <Link v-if="invoice.journal" :href="route('journals.show', invoice.journal.id)" class="mt-4 inline-block text-xs font-medium text-emerald-600 hover:underline">
        Lihat jurnal otomatis →
      </Link>
    </div>

    <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Catat Pembayaran</h3>
        <form @submit.prevent="submitPayment" class="mt-4 space-y-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Tanggal Bayar</label><input v-model="form.payment_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Jumlah</label>
            <input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
            <p v-if="form.errors.amount" class="mt-1 text-xs text-rose-500">{{ form.errors.amount }}</p>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Diterima ke Akun</label>
            <select v-model="form.cash_account_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option v-for="a in cashBankAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
            </select>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showPayModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Simpan</button>
          </div>
        </form>
      </div>
    </div>
    <AttachmentPanel type="invoice" :record-id="invoice.id" :attachments="attachments" :can-upload="can('ar.manage')" />
  </AppLayout>
</template>
