<script setup>
import { useCan } from '@/permissions'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ journals: Object, filters: Object })

const statusBadge = { draft: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300', posted: 'bg-emerald-100 text-emerald-700', void: 'bg-rose-100 text-rose-600' }
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

function filterStatus(status) {
  router.get(route('journals.index'), { ...props.filters, status: status || undefined }, { preserveState: true })
}
</script>

<template>
  <AppLayout>
    <template #title>Jurnal Umum</template>
    <template #actions>
      <a :href="route('journals.export-excel')" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">⬇ Excel</a>
      <Link v-if="can('journal.create')" :href="route('journals.create')" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">
        + Input Jurnal
      </Link>
    </template>

    <div class="mb-4 flex gap-2">
      <button v-for="s in ['', 'draft', 'posted', 'void']" :key="s" @click="filterStatus(s)"
        class="rounded-full px-3 py-1.5 text-xs font-medium"
        :class="filters.status === s || (!filters.status && s === '') ? 'bg-slate-800 text-white' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 ring-1 ring-slate-200'">
        {{ s === '' ? 'Semua' : s }}
      </button>
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3">No. Jurnal</th>
            <th class="px-4 py-3">Tanggal</th>
            <th class="px-4 py-3">Keterangan</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="j in journals.data" :key="j.id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3"><Link :href="route('journals.show', j.id)" class="font-medium text-slate-800 dark:text-slate-100 hover:text-emerald-600">{{ j.journal_number }}</Link></td>
            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ j.journal_date }}</td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ j.description || '-' }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-200">{{ formatIDR(j.total_debit) }}</td>
            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusBadge[j.status]">{{ j.status }}</span></td>
          </tr>
          <tr v-if="journals.data.length === 0"><td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada jurnal.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="journals.links" />
  </AppLayout>
</template>
