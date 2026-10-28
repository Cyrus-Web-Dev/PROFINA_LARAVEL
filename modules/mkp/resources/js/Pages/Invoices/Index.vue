<script setup>
import { useCan } from '@/permissions'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ invoices: Object, filters: Object })
const statusBadge = { draft: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300', unpaid: 'bg-amber-100 text-amber-700', partial: 'bg-blue-100 text-blue-700', paid: 'bg-emerald-100 text-emerald-700', void: 'bg-rose-100 text-rose-600' }
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function filterStatus(status) {
  router.get(route('invoices.index'), { ...props.filters, status: status || undefined }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Piutang Usaha (Invoice)</template>
    <template #actions>
      <Link v-if="can('ar.manage')" :href="route('invoices.create')" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">+ Buat Invoice</Link>
    </template>

    <div class="mb-4 flex gap-2">
      <button v-for="s in ['', 'unpaid', 'partial', 'paid']" :key="s" @click="filterStatus(s)"
        class="rounded-full px-3 py-1.5 text-xs font-medium"
        :class="filters.status === s || (!filters.status && s === '') ? 'bg-slate-800 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 ring-1 ring-slate-200'">
        {{ s === '' ? 'Semua' : s }}
      </button>
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3">No. Invoice</th><th class="px-4 py-3">Pelanggan</th><th class="px-4 py-3">Jatuh Tempo</th>
            <th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Sisa</th><th class="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in invoices.data" :key="inv.id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3"><Link :href="route('invoices.show', inv.id)" class="font-medium text-slate-800 dark:text-slate-100 hover:text-emerald-600">{{ inv.invoice_number }}</Link></td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ inv.customer.name }}</td>
            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ inv.due_date }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ formatIDR(inv.total) }}</td>
            <td class="px-4 py-3 text-right font-mono text-amber-600">{{ formatIDR(inv.total - inv.paid_amount) }}</td>
            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge[inv.status]">{{ inv.status }}</span></td>
          </tr>
          <tr v-if="invoices.data.length === 0"><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada invoice.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="invoices.links" />
  </AppLayout>
</template>
