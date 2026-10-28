<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import { rp, num } from '@/Composables/format'

const props = defineProps({ products: Object, filters: Object, levels: Array, canCost: Boolean })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)

const q = ref(props.filters.q ?? '')
const search = () => router.get(route('products.index'), { q: q.value }, { preserveState: true, replace: true })

const editing = ref(null)
const form = useForm({ sku: '', name: '', unit: 'pcs', description: '', base_price: 0, cost_price: '', min_order_qty: 1, is_active: true, prices: [] })
function openForm(p) {
  editing.value = p ?? 'new'
  form.clearErrors()
  const prices = props.levels.map((l) => {
    const ex = p?.prices.find((x) => x.price_level_id === l.id)
    return { price_level_id: l.id, price: ex?.price ?? '', min_qty: ex?.min_qty ?? '' }
  })
  Object.assign(form, p ? { ...p, cost_price: p.cost_price ?? '', prices } : { sku: '', name: '', unit: 'pcs', description: '', base_price: 0, cost_price: '', min_order_qty: 1, is_active: true, prices })
}
function save() {
  form.transform((d) => ({ ...d, cost_price: d.cost_price === '' ? null : d.cost_price, prices: d.prices.filter((x) => x.price !== '' && x.price !== null).map((x) => ({ ...x, min_qty: x.min_qty === '' ? null : x.min_qty })) }))
  const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) }
  editing.value === 'new' ? form.post(route('products.store'), opts) : form.put(route('products.update', editing.value.id), opts)
}
function remove(p) { if (confirm(`Hapus produk ${p.name}?`)) router.delete(route('products.destroy', p.id), { preserveScroll: true }) }

// level harga
const lvl = useForm({ code: '', name: '', discount_pct: 0 })
const showLevel = ref(false)
const addLevel = () => lvl.post(route('levels.store'), { preserveScroll: true, onSuccess: () => { lvl.reset(); showLevel.value = false } })
function editLevel(l) {
  const name = prompt('Nama level', l.name); if (name === null) return
  const d = prompt('Diskon default level (%) bila produk tak punya harga khusus level', l.discount_pct); if (d === null) return
  router.put(route('levels.update', l.id), { name, discount_pct: d }, { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <Head title="Produk & Harga" />
    <template #title>Produk & Harga</template>
    <template #actions><button v-if="can('products.manage')" class="btn-accent btn-sm" @click="openForm()">+ Produk</button></template>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <form class="flex gap-2" @submit.prevent="search"><input v-model="q" class="input max-w-xs" placeholder="Cari nama / SKU…" /><button class="btn-secondary">Cari</button></form>
      <div class="ml-auto flex flex-wrap items-center gap-2 text-xs text-ink-500">
        Level:
        <button v-for="l in levels" :key="l.id" class="badge-seal" :class="can('prices.manage') ? 'cursor-pointer' : ''" @click="can('prices.manage') && editLevel(l)">{{ l.name }} (−{{ l.discount_pct }}%)</button>
        <button v-if="can('prices.manage')" class="btn-ghost btn-sm" @click="showLevel = true">+ level</button>
      </div>
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>SKU</th><th>Produk</th><th class="text-right">Harga dasar</th><th v-for="l in levels" :key="l.id" class="text-right">{{ l.name }}</th><th class="text-right">MOQ</th><th v-if="canCost" class="text-right">HPP</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in products.data" :key="p.id" :class="p.is_active ? '' : 'opacity-50'">
            <td class="font-mono">{{ p.sku }}</td><td>{{ p.name }}<span class="text-xs text-ink-500"> / {{ p.unit }}</span></td>
            <td class="text-right font-mono">{{ rp(p.base_price) }}</td>
            <td v-for="l in levels" :key="l.id" class="text-right font-mono text-xs">{{ p.prices.find((x) => x.price_level_id === l.id) ? rp(p.prices.find((x) => x.price_level_id === l.id).price) : '—' }}</td>
            <td class="text-right font-mono">{{ num(p.min_order_qty) }}</td>
            <td v-if="canCost" class="text-right font-mono">{{ p.cost_price != null ? rp(p.cost_price) : '—' }}</td>
            <td class="whitespace-nowrap text-right"><template v-if="can('products.manage')"><button class="btn-secondary btn-sm" @click="openForm(p)">Ubah</button> <button class="btn-ghost btn-sm text-red-600" @click="remove(p)">Hapus</button></template></td>
          </tr>
          <tr v-if="!products.data.length"><td :colspan="5 + levels.length + (canCost ? 1 : 0)" class="py-6 text-center text-ink-500">Belum ada produk.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="products.links" />

    <div v-if="editing" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="editing = null">
      <form class="card card-body my-8 w-full max-w-2xl space-y-4" @submit.prevent="save">
        <h2 class="font-serif text-lg font-semibold">{{ editing === 'new' ? 'Produk baru' : 'Ubah produk' }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div><label class="label">SKU</label><input v-model="form.sku" class="input" required /><p class="error-text">{{ form.errors.sku }}</p></div>
          <div><label class="label">Satuan</label><input v-model="form.unit" class="input" required /></div>
          <div class="sm:col-span-2"><label class="label">Nama</label><input v-model="form.name" class="input" required /><p class="error-text">{{ form.errors.name }}</p></div>
          <div><label class="label">Harga dasar (level umum)</label><input v-model="form.base_price" type="number" min="0" step="0.01" class="input" required /></div>
          <div><label class="label">MOQ dasar</label><input v-model="form.min_order_qty" type="number" min="0.001" step="0.001" class="input" required /></div>
          <div v-if="canCost"><label class="label">HPP (rahasia)</label><input v-model="form.cost_price" type="number" min="0" step="0.01" class="input" /></div>
          <label class="flex items-center gap-2 pt-6 text-sm"><input v-model="form.is_active" type="checkbox" class="rounded border-ink-300 text-seal-600" /> Produk aktif</label>
        </div>
        <div v-if="can('prices.manage')">
          <p class="label">Harga & MOQ per level (kosongkan = pakai harga dasar − diskon level)</p>
          <div v-for="(r, i) in form.prices" :key="r.price_level_id" class="mb-2 grid grid-cols-12 items-center gap-2">
            <span class="col-span-4 text-sm">{{ levels.find((l) => l.id === r.price_level_id)?.name }}</span>
            <input v-model="r.price" type="number" min="0" step="0.01" class="input col-span-4" placeholder="Harga" />
            <input v-model="r.min_qty" type="number" min="0" step="0.001" class="input col-span-4" placeholder="MOQ level" />
          </div>
        </div>
        <div><label class="label">Deskripsi</label><textarea v-model="form.description" rows="2" class="input"></textarea></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="editing = null">Batal</button><button class="btn-accent" :disabled="form.processing">Simpan</button></div>
      </form>
    </div>

    <div v-if="showLevel" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showLevel = false">
      <form class="card card-body w-full max-w-sm space-y-3" @submit.prevent="addLevel">
        <h2 class="font-serif text-lg font-semibold">Level harga baru</h2>
        <div><label class="label">Kode</label><input v-model="lvl.code" class="input" required /><p class="error-text">{{ lvl.errors.code }}</p></div>
        <div><label class="label">Nama</label><input v-model="lvl.name" class="input" required /></div>
        <div><label class="label">Diskon default (%)</label><input v-model="lvl.discount_pct" type="number" min="0" max="100" step="0.01" class="input" /></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="showLevel = false">Batal</button><button class="btn-accent">Tambah</button></div>
      </form>
    </div>
  </AppLayout>
</template>
