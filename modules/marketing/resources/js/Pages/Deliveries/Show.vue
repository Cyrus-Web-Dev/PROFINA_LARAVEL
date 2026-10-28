<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { num, statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ note: Object, can: Object })
const n = computed(() => props.note)
const ship = () => { if (confirm('Tandai barang sudah dikirim? Sisa pesanan akan berkurang.')) router.post(route('deliveries.ship', n.value.id), {}, { preserveScroll: true }) }
function receive() { const by = prompt('Nama penerima di tujuan:'); if (by) router.post(route('deliveries.receive', n.value.id), { received_by: by }, { preserveScroll: true }) }
const destroy = () => { if (confirm('Hapus draft surat jalan?')) router.delete(route('deliveries.destroy', n.value.id)) }
</script>

<template>
  <AppLayout>
    <Head :title="n.number" />
    <template #title>{{ n.number }}</template>
    <template #actions><a :href="route('deliveries.pdf', n.id)" class="btn-secondary btn-sm">Cetak PDF</a></template>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <span :class="statusClass(n.status)">{{ statusLabel(n.status) }}</span>
      <Link :href="route('orders.show', n.order.id)" class="badge-slate">{{ n.order.number }}</Link>
      <div class="ml-auto flex gap-2">
        <button v-if="can.ship" class="btn-accent btn-sm" @click="ship">Tandai dikirim</button>
        <button v-if="can.receive" class="btn-primary btn-sm" @click="receive">Catat diterima</button>
        <button v-if="can.delete" class="btn-ghost btn-sm text-red-600" @click="destroy">Hapus</button>
      </div>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card card-body text-sm">
        <p class="label">Pelanggan</p><p class="font-medium">{{ n.customer }}</p>
        <p class="label mt-4">Kirim ke</p><p class="whitespace-pre-line">{{ n.ship_to || '—' }}</p>
        <p class="label mt-4">Tanggal / sopir / kendaraan</p><p>{{ n.delivery_date }} · {{ n.driver || '-' }} · {{ n.vehicle_no || '-' }}</p>
        <template v-if="n.received_by"><p class="label mt-4">Diterima</p><p>{{ n.received_by }} ({{ n.received_at }})</p></template>
      </div>
      <div class="card overflow-x-auto lg:col-span-2">
        <table class="data-table"><thead><tr><th>Barang</th><th class="text-right">Qty</th></tr></thead>
          <tbody><tr v-for="(i, k) in n.items" :key="k"><td>{{ i.description }}</td><td class="text-right font-mono">{{ num(i.qty) }} {{ i.unit }}</td></tr></tbody></table>
      </div>
    </div>
  </AppLayout>
</template>
