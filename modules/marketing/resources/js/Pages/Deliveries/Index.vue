<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import { statusClass, statusLabel } from '@/Composables/format'

const props = defineProps({ notes: Object, filters: Object })
const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '' })
const search = () => router.get(route('deliveries.index'), { ...f }, { preserveState: true, replace: true })
</script>

<template>
  <AppLayout>
    <Head title="Surat Jalan" />
    <template #title>Surat Jalan</template>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
      <input v-model="f.q" class="input max-w-xs" placeholder="Nomor surat jalan…" />
      <select v-model="f.status" class="input max-w-[11rem]" @change="search"><option value="">Semua status</option><option v-for="s in ['draft', 'shipped', 'received']" :key="s" :value="s">{{ statusLabel(s) }}</option></select>
      <button class="btn-secondary">Cari</button>
    </form>
    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nomor</th><th>Sales Order</th><th>Pelanggan</th><th>Tanggal</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="d in notes.data" :key="d.id"><td><Link :href="route('deliveries.show', d.id)" class="font-mono font-medium text-seal-700 hover:underline dark:text-seal-400">{{ d.number }}</Link></td><td class="font-mono">{{ d.order }}</td><td>{{ d.customer }}</td><td>{{ d.delivery_date }}</td><td><span :class="statusClass(d.status)">{{ statusLabel(d.status) }}</span></td></tr>
          <tr v-if="!notes.data.length"><td colspan="5" class="py-6 text-center text-ink-500">Belum ada surat jalan. Buat dari halaman Sales Order.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="notes.links" />
  </AppLayout>
</template>
