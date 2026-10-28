<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, Link } from '@inertiajs/vue3'

defineProps({ fundings: Object, messages: Array, labels: Object })
</script>

<template>
  <AppLayout>
    <Head title="Pengajuan Dana" />
    <template #title>Pengajuan Dana ke Keuangan</template>
    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-ink-300">Setelah putaran gaji disetujui, PAYBRIDGE mengajukan dana ke Keuangan (MKP). Keuangan memutuskan, membuat jurnal, dan mencairkan; hasilnya kembali lewat REST API/webhook. Nominal pencairan <b>harus persis sama</b> dengan total transfer di file bank.</p>
    <div class="grid gap-6 xl:grid-cols-3">
      <section class="card xl:col-span-2 overflow-x-auto"><table class="data-table">
        <thead><tr><th>No.</th><th>Putaran</th><th>Status</th><th class="text-right">Utang gaji</th><th class="text-right">Ditransfer</th><th>Keputusan Keuangan</th></tr></thead>
        <tbody><tr v-for="f in fundings.data" :key="f.id"><td class="font-mono text-xs">{{ f.number }}</td><td><Link :href="route('runs.show', f.run_id)" class="hover:underline">{{ f.title }}</Link></td><td><StatusBadge :status="f.status" /></td>
          <td class="text-right"><Money :value="f.net" /></td><td class="text-right"><Money :value="f.disbursable" /></td>
          <td class="text-xs">{{ f.decided_by ?? '—' }}<span v-if="f.finance_ref" class="font-mono"> · {{ f.finance_ref }}</span><p v-if="f.note" class="italic text-ink-500">“{{ f.note }}”</p><p v-if="f.disbursed_at" class="text-seal-700">dicairkan {{ f.disbursed_at }}</p></td></tr>
          <tr v-if="!fundings.data.length"><td colspan="6" class="py-10 text-center text-ink-400">Belum ada pengajuan.</td></tr></tbody></table>
        <div class="p-3"><Pagination :links="fundings.links" /></div></section>
      <section class="card"><header class="card-header"><h2 class="text-base font-semibold">Pesan dari/ke Keuangan</h2></header>
        <ul class="max-h-[32rem] divide-y divide-ink-100 overflow-y-auto dark:divide-ink-800"><li v-for="m in messages" :key="m.id" class="px-5 py-3 text-sm"><p class="flex items-center gap-2"><span :class="m.direction === 'in' ? 'badge-seal' : 'badge-slate'">{{ m.direction === 'in' ? 'masuk' : 'keluar' }}</span><span class="font-medium">{{ m.subject }}</span></p><p v-if="m.body" class="mt-1 text-xs text-ink-600">{{ m.body }}</p><p class="mt-1 text-[11px] text-ink-400">{{ m.author }} · {{ m.when }}</p></li>
          <li v-if="!messages.length" class="px-5 py-8 text-center text-sm text-ink-400">Belum ada pesan.</li></ul></section>
    </div>
  </AppLayout>
</template>
