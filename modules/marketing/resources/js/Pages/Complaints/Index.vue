<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { reactive, ref, computed } from 'vue'
import { statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ complaints: Object, filters: Object, customers: Array, orders: Array, staff: Array })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '' })
const search = () => router.get(route('complaints.index'), { ...f }, { preserveState: true, replace: true })

const showNew = ref(false)
const nf = useForm({ customer_id: '', sales_order_id: '', category: 'kualitas', priority: 'normal', subject: '', description: '' })
const ordersOfCustomer = computed(() => props.orders.filter((o) => o.customer_id === nf.customer_id))
const create = () => nf.transform((d) => ({ ...d, sales_order_id: d.sales_order_id || null })).post(route('complaints.store'), { preserveScroll: true, onSuccess: () => { nf.reset(); showNew.value = false } })

const editing = ref(null)
const ef = useForm({ status: 'open', assigned_to: '', resolution: '' })
function open(c) { editing.value = c; ef.clearErrors(); ef.status = c.status; ef.assigned_to = c.assigned_to ?? ''; ef.resolution = c.resolution ?? '' }
const saveEdit = () => ef.transform((d) => ({ ...d, assigned_to: d.assigned_to || null })).put(route('complaints.update', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) })
const prio = { low: 'badge-slate', normal: 'badge-amber', high: 'badge-red' }
</script>

<template>
  <AppLayout>
    <Head title="Komplain" />
    <template #title>Komplain Pelanggan</template>
    <template #actions><button v-if="can('complaints.manage')" class="btn-accent btn-sm" @click="showNew = true">+ Komplain</button></template>

    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
      <input v-model="f.q" class="input max-w-xs" placeholder="Nomor / subjek…" />
      <select v-model="f.status" class="input max-w-[11rem]" @change="search"><option value="">Semua status</option><option v-for="s in ['open', 'in_progress', 'resolved', 'closed']" :key="s" :value="s">{{ statusLabel(s) }}</option></select>
      <button class="btn-secondary">Cari</button>
    </form>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Pelanggan</th><th>Subjek</th><th>Kategori</th><th>Prioritas</th><th>PIC</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <tr v-for="c in complaints.data" :key="c.id">
            <td class="font-mono">{{ c.number }}<br><span class="text-xs text-ink-500">{{ c.created_at }}</span></td><td>{{ c.customer }}<br><span class="font-mono text-xs text-ink-500">{{ c.order }}</span></td>
            <td>{{ c.subject }}</td><td>{{ c.category }}</td><td><span :class="prio[c.priority]">{{ c.priority }}</span></td><td>{{ c.assignee || '—' }}</td>
            <td><span :class="statusClass(c.status)">{{ statusLabel(c.status) }}</span></td>
            <td class="text-right"><button v-if="can('complaints.manage')" class="btn-secondary btn-sm" @click="open(c)">Tangani</button></td>
          </tr>
          <tr v-if="!complaints.data.length"><td colspan="8" class="py-6 text-center text-ink-500">Tidak ada komplain.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="complaints.links" />

    <div v-if="showNew" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="showNew = false">
      <form class="card card-body my-8 w-full max-w-xl space-y-4" @submit.prevent="create">
        <h2 class="font-serif text-lg font-semibold">Catat komplain</h2>
        <div><label class="label">Pelanggan</label><select v-model="nf.customer_id" class="input" required><option value="" disabled>Pilih…</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
        <div><label class="label">Terkait sales order (opsional)</label><select v-model="nf.sales_order_id" class="input"><option value="">—</option><option v-for="o in ordersOfCustomer" :key="o.id" :value="o.id">{{ o.number }}</option></select></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="label">Kategori</label><select v-model="nf.category" class="input"><option v-for="k in ['kualitas', 'pengiriman', 'harga', 'layanan', 'lainnya']" :key="k" :value="k">{{ k }}</option></select></div>
          <div><label class="label">Prioritas</label><select v-model="nf.priority" class="input"><option value="low">low</option><option value="normal">normal</option><option value="high">high</option></select></div>
        </div>
        <div><label class="label">Subjek</label><input v-model="nf.subject" class="input" required /><p class="error-text">{{ nf.errors.subject }}</p></div>
        <div><label class="label">Uraian</label><textarea v-model="nf.description" rows="4" class="input" required></textarea><p class="error-text">{{ nf.errors.description }}</p></div>
        <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="showNew = false">Batal</button><button class="btn-accent" :disabled="nf.processing">Simpan</button></div>
      </form>
    </div>

    <div v-if="editing" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="editing = null">
      <form class="card card-body my-8 w-full max-w-xl space-y-4" @submit.prevent="saveEdit">
        <div><h2 class="font-serif text-lg font-semibold">{{ editing.number }} — {{ editing.subject }}</h2><p class="mt-1 whitespace-pre-line text-sm text-ink-500">{{ editing.description }}</p></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="label">Status</label><select v-model="ef.status" class="input"><option v-for="s in ['open', 'in_progress', 'resolved', 'closed']" :key="s" :value="s">{{ statusLabel(s) }}</option></select></div>
          <div><label class="label">PIC</label><select v-model="ef.assigned_to" class="input"><option value="">—</option><option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option></select></div>
        </div>
        <div><label class="label">Penyelesaian</label><textarea v-model="ef.resolution" rows="3" class="input"></textarea></div>
        <p v-for="(m, k) in ef.errors" :key="k" class="error-text">{{ m }}</p>
        <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="editing = null">Batal</button><button class="btn-accent" :disabled="ef.processing">Simpan</button></div>
      </form>
    </div>
  </AppLayout>
</template>
