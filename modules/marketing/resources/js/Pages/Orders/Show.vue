<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { rp, num, statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ order: Object, nextStatuses: Array, canDeliver: Boolean })
const o = computed(() => props.order)
function setStatus(s) { if (s !== 'cancelled' || confirm('Batalkan sales order ini?')) router.post(route('orders.status', o.value.id), { status: s }, { preserveScroll: true }) }
</script>

<template>
  <AppLayout>
    <Head :title="o.number" />
    <template #title>{{ o.number }}</template>
    <template #actions><Link v-if="canDeliver" :href="route('deliveries.create', o.id)" class="btn-accent btn-sm">+ Surat jalan</Link></template>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <span :class="statusClass(o.status)">{{ statusLabel(o.status) }}</span>
      <Link v-if="o.quotation" :href="route('quotations.show', o.quotation.id)" class="badge-slate">dari {{ o.quotation.number }}</Link>
      <div class="ml-auto flex gap-2"><button v-for="s in nextStatuses" :key="s" class="btn-secondary btn-sm" :class="s === 'cancelled' ? 'text-red-600' : ''" @click="setStatus(s)">→ {{ statusLabel(s) }}</button></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card card-body text-sm">
        <p class="label">Pelanggan</p><p class="font-medium">{{ o.customer.name }}</p><p class="text-ink-500">{{ o.customer.company }}</p>
        <p class="label mt-4">Kirim ke</p><p class="whitespace-pre-line">{{ o.ship_to || '—' }}</p>
        <p class="label mt-4">Tanggal SO / sales</p><p>{{ o.order_date }} · {{ o.sales }}</p>
      </div>
      <div class="card overflow-x-auto lg:col-span-2">
        <table class="data-table">
          <thead><tr><th>Item</th><th class="text-right">Dipesan</th><th class="text-right">Terkirim</th><th class="text-right">Harga</th><th class="text-right">Jumlah</th></tr></thead>
          <tbody><tr v-for="i in o.items" :key="i.id"><td>{{ i.description }}</td><td class="text-right font-mono">{{ num(i.qty) }} {{ i.unit }}</td><td class="text-right font-mono" :class="Number(i.delivered_qty) >= Number(i.qty) ? 'text-emerald-600' : ''">{{ num(i.delivered_qty) }}</td><td class="text-right font-mono">{{ rp(i.unit_price) }}</td><td class="text-right font-mono">{{ rp(i.line_total) }}</td></tr></tbody>
          <tfoot>
            <tr><td colspan="4" class="text-right">Subtotal</td><td class="text-right font-mono">{{ rp(o.subtotal) }}</td></tr>
            <tr><td colspan="4" class="text-right">PPN {{ Number(o.tax_pct) }}%</td><td class="text-right font-mono">{{ rp(o.tax_amount) }}</td></tr>
            <tr><td colspan="4" class="text-right font-semibold">Total</td><td class="text-right font-mono font-semibold">{{ rp(o.total) }}</td></tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card mt-6">
      <div class="card-header"><h2 class="font-serif text-base font-semibold">Surat jalan</h2></div>
      <ul class="divide-y divide-ink-100 text-sm dark:divide-ink-800">
        <li v-for="d in o.deliveries" :key="d.id" class="flex items-center justify-between px-5 py-2.5"><Link :href="route('deliveries.show', d.id)" class="font-mono text-seal-700 hover:underline dark:text-seal-400">{{ d.number }}</Link><span>{{ d.delivery_date }}</span><span :class="statusClass(d.status)">{{ statusLabel(d.status) }}</span></li>
        <li v-if="!o.deliveries.length" class="px-5 py-6 text-center text-ink-500">Belum ada surat jalan.</li>
      </ul>
    </div>
  </AppLayout>
</template>
