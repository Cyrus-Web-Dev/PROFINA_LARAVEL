<script setup>
import { reactive } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({ contracts: Object, filters: Object, counts: Object })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const f = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' })
const apply = () => router.get(route('contracts.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true })
const tab = (s) => { f.status = s; apply() }
</script>

<template>
  <AppLayout>
    <Head title="Kontrak Kompensasi" />
    <template #title>Kontrak Kompensasi</template>
    <template #actions><Link v-if="can('contracts.propose')" :href="route('contracts.create')" class="btn-primary btn-sm">Usulkan kontrak</Link></template>

    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-ink-300">Gaji hanya dibayar sesuai kontrak <b>aktif</b> yang sudah melewati verifikasi Payroll dan persetujuan Keuangan (dan Direksi untuk golongan eksekutif). Perubahan = versi baru; riwayat tidak pernah ditimpa.</p>
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <button v-for="[v, l] in [['', 'Semua'], ['pending', `Menunggu (${counts.pending})`], ['active', `Aktif (${counts.active})`], ['rejected', 'Ditolak'], ['expired', 'Berakhir']]" :key="l" class="btn-secondary btn-sm"
        :class="{ '!bg-ink-900 !text-white': (filters.status ?? '') === v }" @click="tab(v)">{{ l }}</button>
      <input v-model="f.search" class="input !w-56" placeholder="Cari nama / NIP…" @keyup.enter="apply" />
    </div>
    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>No. kontrak</th><th>Karyawan</th><th>Ver.</th><th>Golongan</th><th>Berlaku</th><th>Asal</th><th>Status</th><th class="text-right">Gaji pokok</th></tr></thead>
        <tbody>
          <tr v-for="c in contracts.data" :key="c.id">
            <td><Link :href="route('contracts.show', c.id)" class="font-mono text-xs hover:underline">{{ c.number }}</Link></td>
            <td>{{ c.employee }}<p class="font-mono text-xs text-ink-400">{{ c.nip }}</p></td><td>v{{ c.version }}</td><td>{{ c.grade ?? '—' }}</td><td class="num text-xs">{{ c.effective_date }}</td>
            <td><span class="badge-slate">{{ c.source === 'sdm' ? 'Usulan SDM' : 'Payroll' }}</span></td><td><StatusBadge :status="c.status" /></td><td class="text-right"><Money :value="c.base_salary" /></td>
          </tr>
          <tr v-if="!contracts.data.length"><td colspan="8" class="py-10 text-center text-ink-400">Tidak ada kontrak.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="contracts.links" />
  </AppLayout>
</template>
