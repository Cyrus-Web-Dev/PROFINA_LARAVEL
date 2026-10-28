<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import { rp } from '@/Composables/format'

const props = defineProps({ quotation: Object, customers: Array, products: Array, defaults: Object, canOverride: Boolean, maxDiscount: Number })

const blank = () => ({ product_id: '', description: '', unit: 'pcs', qty: 1, unit_price: '', discount_pct: 0 })
const form = useForm({
  customer_id: props.quotation?.customer_id ?? '',
  quote_date: props.quotation?.quote_date ?? props.defaults.quote_date,
  valid_until: props.quotation?.valid_until ?? props.defaults.valid_until,
  tax_pct: props.quotation?.tax_pct ?? props.defaults.tax_pct,
  terms: props.quotation?.terms ?? 'Pembayaran sesuai termin yang disepakati. Harga belum termasuk ongkos kirim kecuali dinyatakan lain.',
  notes: props.quotation?.notes ?? '',
  items: props.quotation?.items?.length ? props.quotation.items.map((i) => ({ ...i })) : [blank()],
})

// info harga & MOQ yang berlaku per baris (dari server; harga akhir tetap ditentukan server saat simpan)
const info = reactive({})
async function lookup(i) {
  const row = form.items[i]
  if (!form.customer_id || !row.product_id) return
  try {
    const { data } = await window.axios.get(route('price-quote'), { params: { customer_id: form.customer_id, product_id: row.product_id } })
    info[i] = data
    row.unit = data.unit
    row.description = row.description || data.name
    row.unit_price = data.price
    if (Number(row.qty) < data.moq) row.qty = data.moq
  } catch (e) { info[i] = { error: 'Gagal mengambil harga.' } }
}
function relookupAll() { form.items.forEach((_, i) => lookup(i)) }
const add = () => form.items.push(blank())
const remove = (i) => { form.items.splice(i, 1); if (!form.items.length) add() }

const line = (r) => Math.round(Number(r.qty || 0) * 1000 * Math.round(Number(r.unit_price || 0) * 100 * (1 - Number(r.discount_pct || 0) / 100))) / 1000 / 100
const subtotal = computed(() => form.items.reduce((s, r) => s + line(r), 0))
const tax = computed(() => Math.round(subtotal.value * Number(form.tax_pct || 0)) / 100)

function save() {
  form.transform((d) => ({ ...d, items: d.items.map((r) => ({ ...r, product_id: r.product_id || null })) }))
  props.quotation ? form.put(route('quotations.update', props.quotation.id)) : form.post(route('quotations.store'))
}
const err = (i, k) => form.errors[`items.${i}.${k}`]
</script>

<template>
  <AppLayout>
    <Head :title="quotation ? 'Ubah ' + quotation.number : 'Penawaran baru'" />
    <template #title>{{ quotation ? 'Ubah ' + quotation.number : 'Penawaran baru' }}</template>

    <form class="space-y-6" @submit.prevent="save">
      <div class="card card-body grid gap-4 sm:grid-cols-4">
        <div class="sm:col-span-2"><label class="label">Pelanggan</label>
          <select v-model="form.customer_id" class="input" required @change="relookupAll"><option value="" disabled>Pilih pelanggan…</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.level }})</option></select>
          <p class="error-text">{{ form.errors.customer_id }}</p></div>
        <div><label class="label">Tanggal</label><input v-model="form.quote_date" type="date" class="input" required /></div>
        <div><label class="label">Berlaku s/d</label><input v-model="form.valid_until" type="date" class="input" required /><p class="error-text">{{ form.errors.valid_until }}</p></div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="font-serif text-base font-semibold">Item</h2><button type="button" class="btn-secondary btn-sm" @click="add">+ Item</button></div>
        <div class="space-y-3 p-5">
          <p v-if="form.errors.items" class="error-text">{{ form.errors.items }}</p>
          <div v-for="(r, i) in form.items" :key="i" class="rounded-md border border-ink-200 p-3 dark:border-ink-800">
            <div class="grid gap-2 sm:grid-cols-12">
              <div class="sm:col-span-5">
                <select v-model="r.product_id" class="input" @change="lookup(i)"><option value="">{{ canOverride ? '— item bebas —' : 'Pilih produk…' }}</option><option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option></select>
                <p class="error-text">{{ err(i, 'product_id') }}</p>
              </div>
              <div class="sm:col-span-2"><input v-model="r.qty" type="number" min="0" step="0.001" class="input" placeholder="Qty" @change="lookup(i)" /><p class="error-text">{{ err(i, 'qty') }}</p></div>
              <div class="sm:col-span-2"><input v-model="r.unit_price" type="number" min="0" step="0.01" class="input" placeholder="Harga" :readonly="!canOverride" :class="canOverride ? '' : 'bg-ink-50 dark:bg-ink-800'" /></div>
              <div class="sm:col-span-2"><input v-model="r.discount_pct" type="number" min="0" max="100" step="0.01" class="input" placeholder="Disk %" /><p class="error-text">{{ err(i, 'discount_pct') }}</p></div>
              <button type="button" class="btn-ghost btn-sm text-red-600 sm:col-span-1" @click="remove(i)">×</button>
            </div>
            <div v-if="!r.product_id && canOverride" class="mt-2 grid gap-2 sm:grid-cols-12"><input v-model="r.description" class="input sm:col-span-9" placeholder="Deskripsi item bebas" /><input v-model="r.unit" class="input sm:col-span-3" placeholder="Satuan" /><p class="error-text sm:col-span-12">{{ err(i, 'description') }}</p></div>
            <p v-if="info[i]?.source" class="mt-1 text-xs text-ink-500">Harga: {{ info[i].source }} · MOQ {{ info[i].moq }} {{ info[i].unit }}</p>
            <p class="mt-1 text-right font-mono text-sm">{{ rp(line(r)) }}</p>
          </div>
          <p v-if="!canOverride" class="text-xs text-ink-500">Harga ditentukan sistem sesuai level pelanggan; diskon maksimum {{ maxDiscount }}%. Server menghitung ulang saat disimpan.</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-body space-y-3 lg:col-span-2">
          <div><label class="label">Syarat & ketentuan</label><textarea v-model="form.terms" rows="3" class="input"></textarea></div>
          <div><label class="label">Catatan</label><textarea v-model="form.notes" rows="2" class="input"></textarea></div>
        </div>
        <div class="card card-body space-y-2 text-sm">
          <div class="flex justify-between"><span>Subtotal</span><span class="font-mono">{{ rp(subtotal) }}</span></div>
          <div class="flex items-center justify-between gap-2"><span>PPN %</span><input v-model="form.tax_pct" type="number" min="0" max="100" step="0.01" class="input w-24 text-right" :disabled="!canOverride" /></div>
          <div class="flex justify-between"><span>PPN</span><span class="font-mono">{{ rp(tax) }}</span></div>
          <div class="flex justify-between border-t border-ink-200 pt-2 text-base font-semibold dark:border-ink-800"><span>Total</span><span class="font-mono">{{ rp(subtotal + tax) }}</span></div>
          <p class="text-xs text-ink-500">Angka final dihitung server.</p>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <Link :href="quotation ? route('quotations.show', quotation.id) : route('quotations.index')" class="btn-ghost">Batal</Link>
        <button class="btn-accent" :disabled="form.processing">Simpan draft</button>
      </div>
    </form>
  </AppLayout>
</template>
