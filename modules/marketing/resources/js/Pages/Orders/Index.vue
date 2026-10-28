<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import { rp, statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ orders: Object, filters: Object })
const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '' })
const search = () => router.get(route('orders.index'), { ...f }, { preserveState: true, replace: true })
</script>

<template>
  <AppLayout>
    <Head title="Sales Order" />
    <template #title>Sales Order</template>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
      <input v-model="f.q" class="input max-w-xs" placeholder="Nomor / pelanggan…" />
      <select v-model="f.status" class="input max-w-[12rem]" @change="search"><option value="">Semua status</option><option v-for="s in ['confirmed', 'in_production', 'ready', 'partial', 'delivered', 'cancelled']" :key="s" :value="s">{{ statusLabel(s) }}</option></select>
      <button class="btn-secondary">Cari</button>
    </form>
    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Pelanggan</th><th>Sales</th><th>Tanggal</th><th class="text-right">Total</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="o in orders.data" :key="o.id"><td><Link :href="route('orders.show', o.id)" class="font-mono font-medium text-seal-700 hover:underline dark:text-seal-400">{{ o.number }}</Link></td><td>{{ o.customer }}</td><td>{{ o.sales }}</td><td>{{ o.order_date }}</td><td class="text-right font-mono">{{ rp(o.total) }}</td><td><span :class="statusClass(o.status)">{{ statusLabel(o.status) }}</span></td></tr>
          <tr v-if="!orders.data.length"><td colspan="6" class="py-6 text-center text-ink-500">Belum ada sales order. SO dibuat otomatis saat penawaran disetujui.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="orders.links" />
  </AppLayout>
</template>
