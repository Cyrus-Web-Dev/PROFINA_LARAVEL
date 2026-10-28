<script setup>
import { reactive, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, usePage } from '@inertiajs/vue3'

const props = defineProps({ entries: Object, filters: Object, last: Object, total: Number, head: String })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const f = reactive({ event: props.filters.event ?? '', actor: props.filters.actor ?? '', subject: props.filters.subject ?? '' })
const apply = () => router.get(route('audit.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true })
const open = ref(null)
</script>

<template>
  <AppLayout>
    <Head title="Jejak Audit" />
    <template #title>Jejak Audit</template>
    <template #actions><button class="btn-primary btn-sm" @click="router.post(route('audit.verify'), {}, { preserveScroll: true })">Verifikasi rantai</button><a :href="route('audit.export')" class="btn-secondary btn-sm">Ekspor CSV</a></template>

    <section class="card card-body mb-5">
      <div class="flex flex-wrap items-center gap-3">
        <span v-if="last" :class="last.ok ? 'badge-green' : 'badge-stamp'" class="!px-3 !py-1 text-sm">{{ last.ok ? '✓ Rantai utuh' : '✕ Rantai rusak' }}</span><span v-else class="badge-slate">Belum diverifikasi</span>
        <span class="text-sm text-ink-500"><span v-if="last">terakhir {{ last.at }} · {{ last.checked }} entri · </span>total {{ total }} entri</span>
      </div>
      <p v-if="last && !last.ok" class="mt-2 text-sm font-medium text-stamp-700">Rusak pada entri #{{ last.broken_at }}: {{ last.reason }}</p>
      <p class="mt-3 text-xs text-ink-500">Setiap peristiwa penting (kontrak, persetujuan, penguncian gaji, pencairan, perubahan regulasi, unduhan laporan) ditulis sebagai rantai hash SHA-256 yang <b>tidak bisa diubah/dihapus</b> (trigger database) dan hash terbarunya dikirim harian ke email auditor. Hash kepala saat ini:</p>
      <p class="mt-1 break-all font-mono text-[11px]">{{ head ?? '—' }}</p>
    </section>

    <div class="mb-4 flex flex-wrap gap-2"><input v-model="f.event" class="input !w-52" placeholder="Event (mis. run.)" @keyup.enter="apply" /><input v-model="f.actor" class="input !w-44" placeholder="Pelaku" @keyup.enter="apply" /><input v-model="f.subject" class="input !w-52" placeholder="Objek (nomor)" @keyup.enter="apply" /><button class="btn-secondary btn-sm" @click="apply">Cari</button></div>
    <div class="card overflow-x-auto"><table class="data-table">
      <thead><tr><th class="text-right">#</th><th>Waktu (UTC)</th><th>Event</th><th>Pelaku</th><th>Objek</th><th>IP</th></tr></thead>
      <tbody><template v-for="e in entries.data" :key="e.seq"><tr class="cursor-pointer" @click="open = open === e.seq ? null : e.seq">
        <td class="num text-right text-xs">{{ e.seq }}</td><td class="num whitespace-nowrap text-xs">{{ e.at.replace('T', ' ').slice(0, 19) }}</td><td><span class="badge-slate font-mono">{{ e.event }}</span></td><td>{{ e.actor ?? 'sistem' }}</td><td class="text-xs">{{ e.subject }}</td><td class="num text-xs text-ink-400">{{ e.ip }}</td></tr>
        <tr v-if="open === e.seq"><td colspan="6" class="bg-ink-50 dark:bg-ink-800/50"><pre class="overflow-x-auto text-[11px]">{{ JSON.stringify(e.payload, null, 2) }}</pre><p class="mt-2 break-all font-mono text-[10px] text-ink-400">prev {{ e.prev }}<br>hash &nbsp;{{ e.hash }}</p></td></tr></template></tbody></table></div>
    <Pagination :links="entries.links" />
  </AppLayout>
</template>
