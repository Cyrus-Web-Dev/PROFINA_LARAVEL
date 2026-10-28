<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { num } from '@/Composables/format'

const props = defineProps({ order: Object })
const form = useForm({
  delivery_date: new Date().toISOString().slice(0, 10),
  driver: '', vehicle_no: '', ship_to: props.order.ship_to ?? '', notes: '',
  items: props.order.items.map((i) => ({ sales_order_item_id: i.id, qty: Number(i.outstanding) })),
})
const save = () => form.post(route('deliveries.store', props.order.id))
</script>

<template>
  <AppLayout>
    <Head title="Surat jalan baru" />
    <template #title>Surat jalan baru — {{ order.number }}</template>
    <form class="space-y-6" @submit.prevent="save">
      <div class="card card-body grid gap-4 sm:grid-cols-4">
        <div><label class="label">Tanggal kirim</label><input v-model="form.delivery_date" type="date" class="input" required /></div>
        <div><label class="label">Sopir</label><input v-model="form.driver" class="input" /></div>
        <div><label class="label">No. kendaraan</label><input v-model="form.vehicle_no" class="input" /></div>
        <div class="sm:col-span-4"><label class="label">Alamat kirim ({{ order.customer }})</label><textarea v-model="form.ship_to" rows="2" class="input"></textarea></div>
      </div>
      <div class="card overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Item</th><th class="text-right">Dipesan</th><th class="text-right">Sudah terkirim</th><th class="text-right">Sisa</th><th class="w-40 text-right">Qty kirim</th></tr></thead>
          <tbody>
            <tr v-for="(i, k) in order.items" :key="i.id">
              <td>{{ i.description }}</td><td class="text-right font-mono">{{ num(i.qty) }} {{ i.unit }}</td><td class="text-right font-mono">{{ num(i.delivered_qty) }}</td><td class="text-right font-mono">{{ num(i.outstanding) }}</td>
              <td><input v-model="form.items[k].qty" type="number" min="0" :max="i.outstanding" step="0.001" class="input text-right" :disabled="Number(i.outstanding) <= 0" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-for="(m, k) in form.errors" :key="k" class="error-text">{{ m }}</p>
      <div class="flex justify-end gap-2"><Link :href="route('orders.show', order.id)" class="btn-ghost">Batal</Link><button class="btn-accent" :disabled="form.processing">Buat surat jalan (draft)</button></div>
    </form>
  </AppLayout>
</template>
