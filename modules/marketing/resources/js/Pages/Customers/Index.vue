<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import { statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ customers: Object, filters: Object, levels: Array, salesUsers: Array, products: Array })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)

const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '', level: props.filters.level ?? '' })
const search = () => router.get(route('customers.index'), { ...f }, { preserveState: true, replace: true })

const editing = ref(null) // null | 'new' | customer
const form = useForm({ name: '', company: '', price_level_id: '', email: '', phone: '', address: '', city: '', npwp: '', payment_terms_days: 0, credit_limit: 0, owner_id: '', status: 'active', notes: '' })
function openForm(c) {
  editing.value = c ?? 'new'
  form.clearErrors()
  Object.assign(form, c ? { ...c, owner_id: c.owner_id ?? '' } : { name: '', company: '', price_level_id: props.levels[0]?.id ?? '', email: '', phone: '', address: '', city: '', npwp: '', payment_terms_days: 0, credit_limit: 0, owner_id: '', status: 'active', notes: '' })
}
function save() {
  const opts = { preserveScroll: true, onSuccess: () => (editing.value = null) }
  editing.value === 'new' ? form.post(route('customers.store'), opts) : form.put(route('customers.update', editing.value.id), opts)
}
function remove(c) { if (confirm(`Hapus pelanggan ${c.name}?`)) router.delete(route('customers.destroy', c.id), { preserveScroll: true }) }

// Harga khusus pelanggan
const pricing = ref(null)
const priceRows = ref([])
async function openPrices(c) {
  pricing.value = c
  const { data } = await window.axios.get(route('customers.prices', c.id))
  priceRows.value = data.map((r) => ({ product_id: r.product_id, price: r.price, min_qty: r.min_qty }))
}
const addPrice = () => priceRows.value.push({ product_id: props.products[0]?.id ?? '', price: 0, min_qty: '' })
function savePrices() {
  router.put(route('customers.prices.sync', pricing.value.id), { prices: priceRows.value.map((r) => ({ ...r, min_qty: r.min_qty === '' ? null : r.min_qty })) }, { preserveScroll: true, onSuccess: () => (pricing.value = null) })
}
</script>

<template>
  <AppLayout>
    <Head title="Pelanggan" />
    <template #title>Pelanggan</template>
    <template #actions><button v-if="can('customers.manage')" class="btn-accent btn-sm" @click="openForm()">+ Pelanggan</button></template>

    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
      <input v-model="f.q" class="input max-w-xs" placeholder="Cari nama / kode / perusahaan…" />
      <select v-model="f.level" class="input max-w-[10rem]" @change="search"><option value="">Semua level</option><option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option></select>
      <select v-model="f.status" class="input max-w-[10rem]" @change="search"><option value="">Semua status</option><option value="prospect">Prospek</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
      <button class="btn-secondary">Cari</button>
    </form>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Kode</th><th>Pelanggan</th><th>Level</th><th>Kontak</th><th>Sales</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <tr v-for="c in customers.data" :key="c.id">
            <td class="font-mono">{{ c.code }}</td>
            <td>{{ c.name }}<br><span class="text-xs text-ink-500">{{ c.company }}</span></td>
            <td><span class="badge-seal">{{ c.level }}</span></td>
            <td class="text-xs">{{ c.phone }}<br>{{ c.email }}</td>
            <td>{{ c.owner || '—' }}</td>
            <td><span :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span></td>
            <td class="whitespace-nowrap text-right">
              <template v-if="can('customers.manage')">
                <button class="btn-secondary btn-sm" @click="openForm(c)">Ubah</button>
                <button class="btn-ghost btn-sm" @click="openPrices(c)">Harga khusus</button>
                <button class="btn-ghost btn-sm text-red-600" @click="remove(c)">Hapus</button>
              </template>
            </td>
          </tr>
          <tr v-if="!customers.data.length"><td colspan="7" class="py-6 text-center text-ink-500">Belum ada pelanggan.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="customers.links" />

    <div v-if="editing" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="editing = null">
      <form class="card card-body my-8 w-full max-w-2xl space-y-4" @submit.prevent="save">
        <h2 class="font-serif text-lg font-semibold">{{ editing === 'new' ? 'Pelanggan baru' : 'Ubah pelanggan' }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div><label class="label">Nama</label><input v-model="form.name" class="input" required /><p class="error-text">{{ form.errors.name }}</p></div>
          <div><label class="label">Perusahaan</label><input v-model="form.company" class="input" /></div>
          <div><label class="label">Level harga</label><select v-model="form.price_level_id" class="input"><option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
          <div><label class="label">Status</label><select v-model="form.status" class="input"><option value="prospect">Prospek</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></div>
          <div><label class="label">Email</label><input v-model="form.email" type="email" class="input" /><p class="error-text">{{ form.errors.email }}</p></div>
          <div><label class="label">Telepon</label><input v-model="form.phone" class="input" /></div>
          <div><label class="label">Kota</label><input v-model="form.city" class="input" /></div>
          <div><label class="label">NPWP</label><input v-model="form.npwp" class="input" /></div>
          <div><label class="label">Termin bayar (hari)</label><input v-model="form.payment_terms_days" type="number" min="0" class="input" /></div>
          <div><label class="label">Batas kredit (Rp)</label><input v-model="form.credit_limit" type="number" min="0" step="0.01" class="input" /></div>
          <div v-if="salesUsers.length" class="sm:col-span-2"><label class="label">Sales penanggung jawab</label><select v-model="form.owner_id" class="input"><option value="">— saya sendiri —</option><option v-for="u in salesUsers" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
        </div>
        <div><label class="label">Alamat</label><textarea v-model="form.address" rows="2" class="input"></textarea></div>
        <div><label class="label">Catatan</label><textarea v-model="form.notes" rows="2" class="input"></textarea></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="editing = null">Batal</button><button class="btn-accent" :disabled="form.processing">Simpan</button></div>
      </form>
    </div>

    <div v-if="pricing" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="pricing = null">
      <div class="card card-body my-8 w-full max-w-2xl space-y-4">
        <div><h2 class="font-serif text-lg font-semibold">Harga khusus — {{ pricing.name }}</h2><p class="text-xs text-ink-500">Menimpa harga level untuk produk yang dipilih. MOQ kosong = ikut MOQ level/produk.</p></div>
        <div v-for="(r, i) in priceRows" :key="i" class="grid grid-cols-12 items-center gap-2">
          <select v-model="r.product_id" class="input col-span-6"><option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} — {{ p.name }}</option></select>
          <input v-model="r.price" type="number" min="0" step="0.01" class="input col-span-3" placeholder="Harga" />
          <input v-model="r.min_qty" type="number" min="0" step="0.001" class="input col-span-2" placeholder="MOQ" />
          <button class="btn-ghost btn-sm col-span-1 text-red-600" @click="priceRows.splice(i, 1)">×</button>
        </div>
        <button class="btn-secondary btn-sm" @click="addPrice">+ Produk</button>
        <div class="flex justify-end gap-2"><button class="btn-ghost" @click="pricing = null">Batal</button><button class="btn-accent" @click="savePrices">Simpan harga khusus</button></div>
      </div>
    </div>
  </AppLayout>
</template>
