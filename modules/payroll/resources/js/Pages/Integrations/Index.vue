<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({ clients: Array, deliveries: Array, inbound: Array, scopes: Object, events: Object, presets: Object, sdm: Object, apiBase: String, credentials: Object })
const open = ref(false)
const form = useForm({ name: '', system: 'keuangan', scopes: [...props.presets.keuangan.scopes], webhook_url: '', events: [...props.presets.keuangan.events] })
const preset = () => { const p = props.presets[form.system]; if (p) { form.scopes = [...p.scopes]; form.events = [...p.events] } }
const create = () => form.post(route('integrations.store'), { preserveScroll: true, onSuccess: () => { open.value = false; form.reset() } })
const post = (name, id) => router.post(route(name, id), {}, { preserveScroll: true })
const destroy = (c) => { if (confirm(`Hapus klien "${c.name}"? Sistem tersebut langsung kehilangan akses.`)) router.delete(route('integrations.destroy', c.id), { preserveScroll: true }) }
const rotate = (c) => { if (confirm('Buat ulang token & rahasia? Yang lama langsung tidak berlaku.')) post('integrations.rotate', c.id) }
const toggle = (c) => router.put(route('integrations.update', c.id), { scopes: c.scopes, webhook_url: c.webhook_url, events: c.events, is_active: !c.active }, { preserveScroll: true })
const copy = (t) => navigator.clipboard?.writeText(t)
const tab = ref('clients')
</script>

<template>
  <AppLayout>
    <Head title="Integrasi API" />
    <template #title>Integrasi API</template>
    <template #actions><button class="btn-primary btn-sm" @click="open = !open">+ Klien API</button></template>

    <div v-if="credentials" class="mb-5 rounded-md border-2 border-seal-500 bg-seal-50 p-4 dark:bg-seal-900/20">
      <p class="font-semibold">Kredensial “{{ credentials.name }}” — salin sekarang, tidak akan ditampilkan lagi</p>
      <div class="mt-2 space-y-2 text-sm">
        <div class="flex items-center gap-2"><span class="w-16 text-xs text-ink-500">Token</span><code class="flex-1 break-all rounded bg-white px-2 py-1 font-mono text-xs dark:bg-ink-900">{{ credentials.token }}</code><button class="btn-secondary btn-sm" @click="copy(credentials.token)">Salin</button></div>
        <div class="flex items-center gap-2"><span class="w-16 text-xs text-ink-500">Rahasia</span><code class="flex-1 break-all rounded bg-white px-2 py-1 font-mono text-xs dark:bg-ink-900">{{ credentials.secret }}</code><button class="btn-secondary btn-sm" @click="copy(credentials.secret)">Salin</button></div>
      </div>
      <p class="mt-2 text-xs text-ink-600">Token = <code>Authorization: Bearer …</code> untuk memanggil API PAYBRIDGE. Rahasia = kunci HMAC untuk memverifikasi header <code>X-Paybridge-Signature</code> pada webhook yang PAYBRIDGE kirim.</p>
    </div>

    <section class="card mb-6">
      <header class="card-header"><div><h2 class="text-base font-semibold">Koneksi ke SDM (PAYBRIDGE → SDM)</h2><p class="text-xs text-ink-500">PAYBRIDGE menarik karyawan & absensi dari REST API SDM, dan menerima webhook dari SDM.</p></div>
        <button class="btn-secondary btn-sm" :disabled="!sdm.configured" @click="router.post(route('integrations.sdm-test'), {}, { preserveScroll: true })">Tes koneksi</button></header>
      <div class="card-body grid gap-3 text-sm sm:grid-cols-3">
        <div><p class="text-xs text-ink-500">API SDM (SDM_URL / SDM_TOKEN)</p><p><span :class="sdm.configured ? 'badge-green' : 'badge-amber'">{{ sdm.configured ? 'Terisi' : 'Belum diatur' }}</span> <span class="break-all font-mono text-xs">{{ sdm.url }}</span></p></div>
        <div><p class="text-xs text-ink-500">Rahasia webhook SDM (SDM_WEBHOOK_SECRET)</p><p><span :class="sdm.secret_set ? 'badge-green' : 'badge-amber'">{{ sdm.secret_set ? 'Terisi' : 'Belum diatur' }}</span></p></div>
        <div><p class="text-xs text-ink-500">Isi di SDM → “Integrasi Payroll” sebagai alamat webhook</p><p class="break-all font-mono text-xs">{{ sdm.webhook_endpoint }}</p></div>
      </div>
    </section>

    <form v-if="open" class="card card-body mb-6 grid gap-4 lg:grid-cols-2" @submit.prevent="create">
      <div class="grid gap-3 sm:grid-cols-2"><div><label class="label">Nama klien</label><input v-model="form.name" class="input" placeholder="mis. MKP Keuangan" /><p v-if="form.errors.name" class="text-xs text-stamp-600">{{ form.errors.name }}</p></div>
        <div><label class="label">Sistem</label><select v-model="form.system" class="input" @change="preset"><option value="keuangan">Keuangan (MKP)</option><option value="sdm">SDM</option><option value="audit">Auditor</option><option value="lainnya">Lainnya</option></select></div>
        <div class="sm:col-span-2"><label class="label">Alamat webhook (opsional)</label><input v-model="form.webhook_url" class="input font-mono text-xs" placeholder="http://127.0.0.1:8001/api/payroll/webhook" /><p v-if="form.errors.webhook_url" class="text-xs text-stamp-600">{{ form.errors.webhook_url }}</p></div></div>
      <div class="grid gap-3 sm:grid-cols-2"><div><p class="label">Scope (izin API)</p><label v-for="(l, k) in scopes" :key="k" class="mb-1 flex items-start gap-2 text-xs"><input v-model="form.scopes" :value="k" type="checkbox" class="mt-0.5 rounded" /><span><code>{{ k }}</code> {{ l }}</span></label></div>
        <div><p class="label">Event webhook</p><label v-for="(l, k) in events" :key="k" class="mb-1 flex items-start gap-2 text-xs"><input v-model="form.events" :value="k" type="checkbox" class="mt-0.5 rounded" /><span><code>{{ k }}</code> {{ l }}</span></label></div></div>
      <div class="lg:col-span-2"><button class="btn-primary" :disabled="form.processing">Buat klien & tampilkan token</button></div>
    </form>

    <div class="mb-4 flex gap-1 border-b border-ink-200 dark:border-ink-800"><button v-for="[k, l] in [['clients', 'Klien API'], ['out', 'Webhook keluar'], ['in', 'Webhook masuk (SDM)']]" :key="k" class="-mb-px border-b-2 px-4 py-2 text-sm font-medium" :class="tab === k ? 'border-ink-900 dark:border-seal-400' : 'border-transparent text-ink-500'" @click="tab = k">{{ l }}</button></div>

    <div v-show="tab === 'clients'" class="space-y-4">
      <article v-for="c in clients" :key="c.id" class="card card-body" :class="{ 'opacity-60': !c.active }">
        <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold">{{ c.name }}</h3><span class="badge-seal">{{ c.system }}</span><span :class="c.active ? 'badge-green' : 'badge-slate'">{{ c.active ? 'aktif' : 'nonaktif' }}</span><span v-if="c.failed" class="badge-stamp">{{ c.failed }} webhook tertunda</span>
          <span class="ml-auto font-mono text-xs text-ink-400">{{ c.prefix }}…</span></div>
        <p class="mt-2 text-xs text-ink-500">Scope: <code v-for="s in c.scopes" :key="s" class="mr-1 rounded bg-ink-100 px-1 dark:bg-ink-800">{{ s }}</code></p>
        <p v-if="c.webhook_url" class="mt-1 text-xs text-ink-500">Webhook → <span class="font-mono">{{ c.webhook_url }}</span> · {{ c.events.length }} event</p>
        <p class="mt-1 text-xs text-ink-400">Terakhir dipakai: {{ c.last_used ?? 'belum pernah' }}<span v-if="c.last_ip"> dari {{ c.last_ip }}</span></p>
        <div class="mt-3 flex flex-wrap gap-2"><button v-if="c.webhook_url" class="btn-secondary btn-sm" @click="post('integrations.ping', c.id)">Ping webhook</button><button class="btn-secondary btn-sm" @click="rotate(c)">Buat ulang token</button><button class="btn-ghost btn-sm" @click="toggle(c)">{{ c.active ? 'Nonaktifkan' : 'Aktifkan' }}</button><button class="btn-ghost btn-sm text-stamp-600" @click="destroy(c)">Hapus</button></div>
      </article>
      <p v-if="!clients.length" class="card card-body text-sm text-ink-400">Belum ada klien. Buat satu untuk Keuangan (MKP) dan satu untuk SDM. Dokumentasi endpoint: <code>docs/INTEGRASI.md</code> · basis URL <code>{{ apiBase }}</code></p>
    </div>

    <section v-show="tab === 'out'" class="card overflow-x-auto"><table class="data-table"><thead><tr><th>Waktu</th><th>Klien</th><th>Event</th><th>Status</th><th>HTTP</th><th>Percobaan</th><th></th></tr></thead>
      <tbody><tr v-for="d in deliveries" :key="d.id"><td class="num text-xs">{{ d.at }}</td><td>{{ d.client }}</td><td class="font-mono text-xs">{{ d.event }}</td><td><StatusBadge :status="d.status" /><p v-if="d.error" class="text-[10px] text-stamp-600">{{ d.error }}</p></td><td class="num">{{ d.code ?? '—' }}</td><td class="num">{{ d.attempts }}</td>
        <td class="text-right"><button v-if="d.status !== 'delivered'" class="btn-secondary btn-sm" @click="post('integrations.resend', d.id)">Kirim ulang</button></td></tr><tr v-if="!deliveries.length"><td colspan="7" class="py-8 text-center text-ink-400">Belum ada pengiriman.</td></tr></tbody></table></section>

    <section v-show="tab === 'in'" class="card overflow-x-auto"><table class="data-table"><thead><tr><th>Waktu</th><th>Sumber</th><th>Event</th><th>Status</th><th></th></tr></thead>
      <tbody><tr v-for="e in inbound" :key="e.id"><td class="num text-xs">{{ e.at }}</td><td>{{ e.source }}</td><td class="font-mono text-xs">{{ e.event }}</td><td><StatusBadge :status="e.status" /><p v-if="e.error" class="text-[10px] text-stamp-600">{{ e.error }}</p></td>
        <td class="text-right"><button v-if="e.status !== 'processed'" class="btn-secondary btn-sm" @click="post('integrations.replay', e.id)">Proses ulang</button></td></tr><tr v-if="!inbound.length"><td colspan="5" class="py-8 text-center text-ink-400">Belum ada webhook masuk.</td></tr></tbody></table></section>
  </AppLayout>
</template>
