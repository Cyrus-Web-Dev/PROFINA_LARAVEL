<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { rp, num, statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ quotation: Object, can: Object, margin: Object })
const q = computed(() => props.quotation)
const act = (name, data = {}) => router.post(route(name, q.value.id), data, { preserveScroll: true })
function approve() { if (confirm('Pelanggan menyetujui penawaran ini? Sales Order akan dibuat otomatis.')) router.post(route('quotations.approve', q.value.id)) }
function reject() { const reason = prompt('Alasan ditolak (opsional):'); if (reason !== null) act('quotations.reject', { reason }) }
function destroy() { if (confirm('Hapus draft ini?')) router.delete(route('quotations.destroy', q.value.id)) }
</script>

<template>
  <AppLayout>
    <Head :title="q.number" />
    <template #title>{{ q.number }}</template>
    <template #actions><a :href="route('quotations.pdf', q.id)" class="btn-secondary btn-sm">Unduh PDF</a></template>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <span :class="statusClass(q.status)">{{ statusLabel(q.status) }}</span>
      <Link v-if="q.order" :href="route('orders.show', q.order.id)" class="badge-green">→ {{ q.order.number }}</Link>
      <div class="ml-auto flex flex-wrap gap-2">
        <Link v-if="can.edit" :href="route('quotations.edit', q.id)" class="btn-secondary btn-sm">Ubah</Link>
        <button v-if="can.send" class="btn-primary btn-sm" @click="act('quotations.send')">Tandai terkirim</button>
        <button v-if="can.reopen" class="btn-secondary btn-sm" @click="act('quotations.reopen')">Tarik ke draft</button>
        <button v-if="can.decide" class="btn-accent btn-sm" @click="approve">Setujui → buat Sales Order</button>
        <button v-if="can.decide" class="btn-ghost btn-sm text-red-600" @click="reject">Tolak</button>
        <button v-if="can.edit" class="btn-ghost btn-sm text-red-600" @click="destroy">Hapus</button>
      </div>
    </div>
    <p v-if="q.reject_reason" class="mb-4 rounded-md bg-red-50 px-4 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">Ditolak: {{ q.reject_reason }}</p>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="card card-body text-sm lg:col-span-1">
        <p class="label">Pelanggan</p><p class="font-medium">{{ q.customer.name }}</p><p class="text-ink-500">{{ q.customer.company }} · level {{ q.customer.level }}</p><p class="mt-1 whitespace-pre-line text-ink-500">{{ q.customer.address }}</p>
        <p class="label mt-4">Tanggal / berlaku s/d</p><p>{{ q.quote_date }} / {{ q.valid_until }}</p>
        <p class="label mt-4">Sales</p><p>{{ q.sales }}</p>
      </div>
      <div class="card lg:col-span-2 overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Deskripsi</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Disk</th><th class="text-right">Jumlah</th></tr></thead>
          <tbody><tr v-for="(i, k) in q.items" :key="k"><td>{{ i.description }}</td><td class="text-right font-mono">{{ num(i.qty) }} {{ i.unit }}</td><td class="text-right font-mono">{{ rp(i.unit_price) }}</td><td class="text-right">{{ Number(i.discount_pct) || '' }}</td><td class="text-right font-mono">{{ rp(i.line_total) }}</td></tr></tbody>
          <tfoot>
            <tr><td colspan="4" class="text-right">Subtotal</td><td class="text-right font-mono">{{ rp(q.subtotal) }}</td></tr>
            <tr><td colspan="4" class="text-right">PPN {{ Number(q.tax_pct) }}%</td><td class="text-right font-mono">{{ rp(q.tax_amount) }}</td></tr>
            <tr><td colspan="4" class="text-right font-semibold">Total</td><td class="text-right font-mono font-semibold">{{ rp(q.total) }}</td></tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div v-if="margin" class="card card-body mt-4 border-seal-300 text-sm"><p class="label">Margin (hanya terlihat pemegang izin HPP)</p>HPP {{ rp(margin.cost) }} · laba kotor {{ rp(margin.profit) }} ({{ margin.pct }}%)</div>
    <div v-if="q.terms || q.notes" class="card card-body mt-4 text-sm"><p v-if="q.terms" class="whitespace-pre-line">{{ q.terms }}</p><p v-if="q.notes" class="mt-2 whitespace-pre-line text-ink-500">{{ q.notes }}</p></div>
  </AppLayout>
</template>
