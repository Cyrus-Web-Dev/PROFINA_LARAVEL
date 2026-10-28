<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({ clients: Array, deliveries: Array, inbox: Array, runs: Array, scopes: Object, events: Object, status: Object, credentials: Object })
const open = ref(false)
const form = useForm({
  name: 'PAYBRIDGE Payroll', scopes: ['employees:read', 'employees:sensitive', 'attendance:read'], webhook_url: '',
  events: ['employee.created', 'employee.updated', 'employee.status_changed', 'employee.bank_changed', 'employee.compensation_changed'],
})
const create = () => form.post(route('integrations.payroll.store'), { preserveScroll: true, onSuccess: () => { open.value = false } })
const post = (name, id) => router.post(route(name, id), {}, { preserveScroll: true })
const copy = (t) => navigator.clipboard?.writeText(t)
const rotate = (c) => { if (confirm('Buat ulang token & rahasia? Yang lama langsung tidak berlaku.')) post('integrations.payroll.rotate', c.id) }
const destroy = (c) => { if (confirm(`Hapus klien "${c.name}"? Payroll langsung kehilangan akses.`)) router.delete(route('integrations.payroll.destroy', c.id), { preserveScroll: true }) }
const toggle = (c) => router.put(route('integrations.payroll.update', c.id), { scopes: c.scopes, webhook_url: c.webhook_url, events: c.events, is_active: !c.active }, { preserveScroll: true })
const tab = ref('clients')
const badge = { delivered: 'badge-green', processed: 'badge-green', pending: 'badge-amber', received: 'badge-amber', failed: 'badge-red', ignored: 'badge-slate' }
</script>

<template>
  <AppLayout>
    <Head title="Integrasi Payroll" />
    <template #title>Integrasi Payroll (PAYBRIDGE)</template>
    <template #actions><button class="btn-accent btn-sm" @click="open = !open">+ Klien Payroll</button></template>

    <div v-if="credentials" class="mb-5 rounded-md border-2 border-seal-500 bg-seal-50 p-4">
      <p class="font-semibold">Kredensial “{{ credentials.name }}” — salin sekarang, tidak ditampilkan lagi</p>
      <div class="mt-2 space-y-2 text-sm">
        <div class="flex items-center gap-2"><span class="w-28 text-xs text-ink-500">SDM_TOKEN</span><code class="flex-1 break-all rounded bg-white px-2 py-1 font-mono text-xs">{{ credentials.token }}</code><button class="btn-secondary btn-sm" @click="copy(credentials.token)">Salin</button></div>
        <div class="flex items-center gap-2"><span class="w-28 text-xs text-ink-500">SDM_WEBHOOK_SECRET</span><code class="flex-1 break-all rounded bg-white px-2 py-1 font-mono text-xs">{{ credentials.secret }}</code><button class="btn-secondary btn-sm" @click="copy(credentials.secret)">Salin</button></div>
      </div>
      <p class="mt-2 text-xs text-ink-600">Tempel keduanya ke <code>.env</code> PAYBRIDGE (<code>SDM_TOKEN</code> &amp; <code>SDM_WEBHOOK_SECRET</code>), lalu <code>php artisan config:clear</code>.</p>
    </div>

    <section class="card mb-6">
      <header class="card-header"><div><h2 class="text-base font-semibold">Status koneksi</h2><p class="text-xs text-ink-500">SDM adalah sumber kebenaran karyawan, absensi &amp; cuti. Gaji dihitung, dikontrol, dan disetujui di PAYBRIDGE.</p></div></header>
      <div class="card-body grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div><p class="text-xs text-ink-500">API untuk Payroll</p><p class="break-all font-mono text-xs">{{ status.api_base }}</p></div>
        <div><p class="text-xs text-ink-500">Alamat webhook dari Payroll (isi di PAYBRIDGE)</p><p class="break-all font-mono text-xs">{{ status.webhook_endpoint }}</p></div>
        <div><p class="text-xs text-ink-500">PAYROLL_WEBHOOK_SECRET</p><span :class="status.secret_set ? 'badge-green' : 'badge-amber'">{{ status.secret_set ? 'Terisi' : 'Belum diatur' }}</span></div>
        <div><p class="text-xs text-ink-500">Modul payroll bawaan SDM</p><span :class="status.external ? 'badge-seal' : 'badge-slate'">{{ status.external ? 'Dikunci (dialihkan ke PAYBRIDGE)' : 'Masih aktif' }}</span></div>
      </div>
      <p v-if="!status.unrecorded_as_alpha" class="border-t border-ink-100 px-5 py-3 text-xs text-ink-500">Hari kerja tanpa catatan absensi <b>tidak</b> dihitung alpha (SDM_COUNT_UNRECORDED_AS_ALPHA=false). Payroll tetap mendapat angka <code>unrecorded_days</code> dan menandai karyawan tanpa aktivitas sama sekali sebagai indikasi karyawan fiktif.</p>
    </section>

    <form v-if="open" class="card card-body mb-6 grid gap-4 lg:grid-cols-2" @submit.prevent="create">
      <div class="space-y-3"><div><label class="label">Nama klien</label><input v-model="form.name" class="input" /></div>
        <div><label class="label">Alamat webhook PAYBRIDGE</label><input v-model="form.webhook_url" class="input font-mono text-xs" placeholder="http://127.0.0.1:8003/api/sdm/webhook" /><p v-if="form.errors.webhook_url" class="text-xs text-red-600">{{ form.errors.webhook_url }}</p></div></div>
      <div class="grid gap-3 sm:grid-cols-2"><div><p class="label">Scope</p><label v-for="(l, k) in scopes" :key="k" class="mb-1 flex items-start gap-2 text-xs"><input v-model="form.scopes" :value="k" type="checkbox" class="mt-0.5 rounded" /><span><code>{{ k }}</code> {{ l }}</span></label></div>
        <div><p class="label">Event</p><label v-for="(l, k) in events" :key="k" class="mb-1 flex items-start gap-2 text-xs"><input v-model="form.events" :value="k" type="checkbox" class="mt-0.5 rounded" /><span><code>{{ k }}</code> {{ l }}</span></label></div></div>
      <div class="lg:col-span-2"><button class="btn-primary" :disabled="form.processing">Buat klien &amp; tampilkan token</button></div>
    </form>

    <div class="mb-4 flex gap-1 border-b border-ink-200"><button v-for="[k, l] in [['clients', 'Klien'], ['out', 'Webhook keluar'], ['in', 'Webhook masuk'], ['slips', 'Slip gaji terbit']]" :key="k" class="-mb-px border-b-2 px-4 py-2 text-sm font-medium" :class="tab === k ? 'border-ink-900' : 'border-transparent text-ink-500'" @click="tab = k">{{ l }}</button></div>

    <div v-show="tab === 'clients'" class="space-y-4">
      <article v-for="c in clients" :key="c.id" class="card card-body" :class="{ 'opacity-60': !c.active }">
        <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold">{{ c.name }}</h3><span :class="c.active ? 'badge-green' : 'badge-slate'">{{ c.active ? 'aktif' : 'nonaktif' }}</span><span v-if="c.failed" class="badge-red">{{ c.failed }} webhook tertunda</span><span class="ml-auto font-mono text-xs text-ink-400">{{ c.prefix }}…</span></div>
        <p class="mt-2 text-xs text-ink-500">Scope: <code v-for="s in c.scopes" :key="s" class="mr-1 rounded bg-ink-100 px-1">{{ s }}</code></p>
        <p v-if="c.webhook_url" class="mt-1 text-xs text-ink-500">Webhook → <span class="font-mono">{{ c.webhook_url }}</span> · {{ c.events.length }} event</p>
        <p class="mt-1 text-xs text-ink-400">Terakhir dipakai: {{ c.last_used ?? 'belum pernah' }}<span v-if="c.last_ip"> dari {{ c.last_ip }}</span></p>
        <div class="mt-3 flex flex-wrap gap-2"><button v-if="c.webhook_url" class="btn-secondary btn-sm" @click="post('integrations.payroll.ping', c.id)">Ping webhook</button><button class="btn-secondary btn-sm" @click="rotate(c)">Buat ulang token</button><button class="btn-ghost btn-sm" @click="toggle(c)">{{ c.active ? 'Nonaktifkan' : 'Aktifkan' }}</button><button class="btn-ghost btn-sm text-red-600" @click="destroy(c)">Hapus</button></div>
      </article>
      <p v-if="!clients.length" class="card card-body text-sm text-ink-400">Belum ada klien. Buat satu untuk PAYBRIDGE, lalu salin token &amp; rahasianya ke .env PAYBRIDGE.</p>
    </div>

    <section v-show="tab === 'out'" class="card overflow-x-auto"><table class="data-table"><thead><tr><th>Waktu</th><th>Klien</th><th>Event</th><th>Status</th><th>HTTP</th><th>Coba</th><th></th></tr></thead>
      <tbody><tr v-for="d in deliveries" :key="d.id"><td class="num text-xs">{{ d.at }}</td><td>{{ d.client }}</td><td class="font-mono text-xs">{{ d.event }}</td><td><span :class="badge[d.status]">{{ d.status }}</span><p v-if="d.error" class="text-[10px] text-red-600">{{ d.error }}</p></td><td class="num">{{ d.code ?? '—' }}</td><td class="num">{{ d.attempts }}</td>
        <td class="text-right"><button v-if="d.status !== 'delivered'" class="btn-secondary btn-sm" @click="post('integrations.payroll.resend', d.id)">Kirim ulang</button></td></tr><tr v-if="!deliveries.length"><td colspan="7" class="py-8 text-center text-ink-400">Belum ada pengiriman.</td></tr></tbody></table></section>

    <section v-show="tab === 'in'" class="card overflow-x-auto"><table class="data-table"><thead><tr><th>Waktu</th><th>Event</th><th>Status</th></tr></thead>
      <tbody><tr v-for="e in inbox" :key="e.id"><td class="num text-xs">{{ e.at }}</td><td class="font-mono text-xs">{{ e.event }}</td><td><span :class="badge[e.status]">{{ e.status }}</span><p v-if="e.error" class="text-[10px] text-red-600">{{ e.error }}</p></td></tr><tr v-if="!inbox.length"><td colspan="3" class="py-8 text-center text-ink-400">Belum ada webhook masuk dari Payroll.</td></tr></tbody></table></section>

    <section v-show="tab === 'slips'" class="card overflow-x-auto"><table class="data-table"><thead><tr><th>Putaran</th><th>Periode</th><th class="text-right">Slip terbit</th><th>Terbit</th></tr></thead>
      <tbody><tr v-for="r in runs" :key="r.run_number"><td class="font-mono text-xs">{{ r.run_number }}</td><td class="num">{{ String(r.period_month).padStart(2, '0') }}/{{ r.period_year }}</td><td class="num text-right">{{ r.payslips }}</td><td class="num text-xs">{{ r.published_at }}</td></tr><tr v-if="!runs.length"><td colspan="4" class="py-8 text-center text-ink-400">Belum ada slip yang diterbitkan Payroll.</td></tr></tbody></table>
      <p class="px-5 pb-4 text-xs text-ink-400">Hanya jumlah per putaran yang ditampilkan di sini; nominal per orang hanya terlihat oleh karyawan yang bersangkutan dan pihak berwenang di PAYBRIDGE.</p></section>
  </AppLayout>
</template>
