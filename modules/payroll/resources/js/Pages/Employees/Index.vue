<script setup>
import { reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({ employees: Object, departments: Array, filters: Object, sdmConfigured: Boolean, lastSync: String })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const f = reactive({ search: props.filters.search ?? '', department: props.filters.department ?? '', status: props.filters.status ?? '', contract: props.filters.contract ?? '' })
const apply = () => router.get(route('employees.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true })
const sync = () => router.post(route('employees.sync'), {}, { preserveScroll: true })
</script>

<template>
  <AppLayout>
    <Head title="Karyawan" />
    <template #title>Karyawan <span class="ml-2 text-xs font-sans font-normal text-ink-400">cermin dari SDM · baca-saja</span></template>
    <template #actions><button v-if="can('sync.run')" class="btn-secondary btn-sm" :disabled="!sdmConfigured" @click="sync">Sinkron dari SDM</button></template>

    <div class="mb-4 flex flex-wrap gap-2">
      <input v-model="f.search" class="input !w-56" placeholder="Cari nama / NIP…" @keyup.enter="apply" />
      <select v-model="f.department" class="input !w-48" @change="apply"><option value="">Semua departemen</option><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select>
      <select v-model="f.status" class="input !w-40" @change="apply"><option value="">Semua status</option><option value="active">Aktif</option><option value="resigned">Resign</option><option value="terminated">Diberhentikan</option></select>
      <select v-model="f.contract" class="input !w-52" @change="apply"><option value="">Semua kontrak</option><option value="none">Belum punya kontrak aktif</option></select>
    </div>
    <p v-if="!sdmConfigured" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">Koneksi SDM belum diatur. Isi <code>SDM_URL</code> &amp; <code>SDM_TOKEN</code> di .env (lihat docs/INTEGRASI.md).</p>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>NIP</th><th>Nama</th><th>Departemen</th><th>Status</th><th>PTKP</th><th>Kontrak aktif</th><th class="text-right">Gaji pokok</th></tr></thead>
        <tbody>
          <tr v-for="e in employees.data" :key="e.id">
            <td class="font-mono text-xs">{{ e.nip }}</td>
            <td><Link :href="route('employees.show', e.id)" class="font-medium hover:underline">{{ e.full_name }}</Link><p class="text-xs text-ink-400">{{ e.position }}</p></td>
            <td>{{ e.department ?? '—' }}</td>
            <td><span :class="e.status === 'active' ? 'badge-green' : 'badge-slate'">{{ e.status }}</span></td>
            <td class="num text-xs">{{ e.ptkp }}<span v-if="!e.has_npwp" class="ml-1 text-stamp-600" title="Tanpa NPWP: tarif +20%">·tanpa NPWP</span></td>
            <td><span v-if="e.contract" class="font-mono text-xs">{{ e.contract.number }}</span><span v-else-if="e.status === 'active'" class="badge-stamp">Belum ada</span><span v-else>—</span></td>
            <td class="text-right"><Money v-if="e.contract && e.contract.base_salary != null" :value="e.contract.base_salary" /><span v-else class="text-ink-300">—</span></td>
          </tr>
          <tr v-if="!employees.data.length"><td colspan="7" class="py-10 text-center text-ink-400">Tidak ada data. {{ sdmConfigured ? 'Klik “Sinkron dari SDM”.' : '' }}</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="employees.links" />
    <p v-if="lastSync" class="mt-2 text-xs text-ink-400">Sinkron terakhir: {{ lastSync }}</p>
  </AppLayout>
</template>
