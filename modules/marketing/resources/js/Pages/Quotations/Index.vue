<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { reactive } from 'vue'
import { rp, statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ quotations: Object, filters: Object })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '' })
const search = () => router.get(route('quotations.index'), { ...f }, { preserveState: true, replace: true })
</script>

<template>
  <AppLayout>
    <Head title="Penawaran" />
    <template #title>Penawaran (Quotation)</template>
    <template #actions><Link v-if="can('quotations.create')" :href="route('quotations.create')" class="btn-accent btn-sm">+ Penawaran</Link></template>

    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
      <input v-model="f.q" class="input max-w-xs" placeholder="Nomor / pelanggan…" />
      <select v-model="f.status" class="input max-w-[11rem]" @change="search"><option value="">Semua status</option><option v-for="s in ['draft', 'sent', 'approved', 'rejected', 'expired']" :key="s" :value="s">{{ statusLabel(s) }}</option></select>
      <button class="btn-secondary">Cari</button>
    </form>
    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Pelanggan</th><th>Sales</th><th>Tanggal</th><th>Berlaku s/d</th><th class="text-right">Total</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="x in quotations.data" :key="x.id">
            <td><Link :href="route('quotations.show', x.id)" class="font-mono font-medium text-seal-700 hover:underline dark:text-seal-400">{{ x.number }}</Link></td>
            <td>{{ x.customer }}</td><td>{{ x.sales }}</td><td>{{ x.quote_date }}</td><td>{{ x.valid_until }}</td>
            <td class="text-right font-mono">{{ rp(x.total) }}</td><td><span :class="statusClass(x.status)">{{ statusLabel(x.status) }}</span></td>
          </tr>
          <tr v-if="!quotations.data.length"><td colspan="7" class="py-6 text-center text-ink-500">Belum ada penawaran.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="quotations.links" />
  </AppLayout>
</template>
