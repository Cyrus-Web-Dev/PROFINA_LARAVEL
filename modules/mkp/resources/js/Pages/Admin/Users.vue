<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import Pagination from '@/Components/Pagination.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
  users: { type: Object, required: true },
  counts: { type: Object, required: true },
  filters: { type: Object, required: true },
  roles: { type: Array, required: true },
  positions: { type: Array, required: true },
  permissionOptions: { type: Array, required: true },
})

const inputClass = 'mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm'
const labelClass = 'text-xs font-medium text-slate-500 dark:text-slate-400'

const tabs = [
  { key: 'pending', label: 'Menunggu' },
  { key: 'active', label: 'Aktif' },
  { key: 'inactive', label: 'Nonaktif' },
  { key: 'rejected', label: 'Ditolak' },
  { key: 'all', label: 'Semua' },
]
const statusStyle = {
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  inactive: 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  rejected: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
}
const statusLabel = { pending: 'Menunggu', active: 'Aktif', inactive: 'Nonaktif', rejected: 'Ditolak' }

const search = ref(props.filters.search ?? '')
let timer = null
function go(status = props.filters.status) {
  router.get(route('admin.users.index'), { status, search: search.value || undefined }, { preserveState: true, replace: true })
}
function onSearch() {
  clearTimeout(timer)
  timer = setTimeout(() => go(), 350)
}
function countFor(key) {
  return key === 'all' ? Object.values(props.counts).reduce((a, b) => a + b, 0) : props.counts[key] ?? 0
}

// ---------- modal ----------
const modal = ref(null) // { mode: 'approve'|'access'|'reject'|'deactivate'|'message', user }
const form = useForm({ role: 'staff', position: '', permissions: [], note: '', reason: '', subject: '', message: '' })

const titles = {
  approve: 'Setujui pendaftaran',
  access: 'Ubah role & hak akses',
  reject: 'Tolak pendaftaran',
  deactivate: 'Nonaktifkan akun',
  message: 'Kirim pesan ke email',
}

function presetFor(key) {
  return [...(props.positions.find((p) => p.key === key)?.permissions ?? [])]
}
function applyPreset() {
  form.permissions = presetFor(form.position)
}
function open(mode, user) {
  form.reset()
  form.clearErrors()
  modal.value = { mode, user }
  if (mode === 'approve') {
    form.role = 'staff'
    form.position = ''
    form.permissions = []
  } else if (mode === 'access') {
    form.role = user.role ?? 'staff'
    form.position = user.position ?? ''
    form.permissions = [...user.permissions]
  } else if (mode === 'message') {
    form.subject = ''
    form.message = ''
  }
}
function onPositionChange() {
  if (modal.value?.mode === 'approve') applyPreset() // saat menyetujui, centang otomatis mengikuti posisi
}
function close() {
  modal.value = null
}

function submit() {
  const { mode, user } = modal.value
  const opts = { preserveScroll: true, onSuccess: close }
  const access = (d) => ({ role: d.role, position: d.position || null, permissions: d.role === 'staff' ? d.permissions : [], note: d.note || null })

  if (mode === 'approve') form.transform(access).post(route('admin.users.approve', user.id), opts)
  else if (mode === 'access') form.transform(access).put(route('admin.users.access', user.id), opts)
  else if (mode === 'reject') form.transform((d) => ({ reason: d.reason })).post(route('admin.users.reject', user.id), opts)
  else if (mode === 'deactivate') form.transform((d) => ({ reason: d.reason || null })).post(route('admin.users.deactivate', user.id), opts)
  else if (mode === 'message') form.transform((d) => ({ subject: d.subject, message: d.message })).post(route('admin.users.message', user.id), opts)
}

function activate(user) {
  if (confirm(`Aktifkan kembali akun ${user.name}?`)) {
    router.post(route('admin.users.activate', user.id), {}, { preserveScroll: true })
  }
}
</script>

<template>
  <AppLayout>
    <template #title>Kelola Pengguna</template>

    <div class="mx-auto max-w-5xl">
      <!-- Tab status + pencarian -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
          <button v-for="t in tabs" :key="t.key" type="button" class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
            :class="filters.status === t.key ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
            @click="go(t.key)">
            {{ t.label }}
            <span class="ml-1 rounded-full px-1.5 text-xs" :class="t.key === 'pending' && countFor('pending') > 0 ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300'">{{ countFor(t.key) }}</span>
          </button>
        </div>
        <input v-model="search" type="search" placeholder="Cari nama / email…" class="w-full rounded-lg border-slate-200 text-sm sm:w-64 dark:border-slate-700" @input="onSearch" />
      </div>

      <!-- Daftar -->
      <div class="mt-4 space-y-3">
        <p v-if="users.data.length === 0" class="rounded-2xl bg-white px-4 py-10 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700">
          Tidak ada pengguna pada tab ini.
        </p>

        <div v-for="u in users.data" :key="u.id" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex min-w-0 flex-1 items-center gap-3">
              <UserAvatar :src="u.avatar_url" :name="u.name" :size="44" />
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                  {{ u.name }} <span v-if="u.is_self" class="text-xs font-normal text-slate-400">(kamu)</span>
                </p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ u.email }}<span v-if="u.phone"> · {{ u.phone }}</span></p>
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[u.status]">{{ statusLabel[u.status] }}</span>
                  <span v-if="u.status !== 'pending' && u.role" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ u.role_label }}</span>
                  <span v-if="u.position_label" class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-700 dark:bg-sky-950 dark:text-sky-300">{{ u.position_label }}</span>
                  <span class="text-xs text-slate-400">Daftar {{ u.registered_at }}</span>
                </div>
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              <template v-if="u.status === 'pending'">
                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700" @click="open('approve', u)">Tinjau & setujui</button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50 dark:ring-rose-900 dark:hover:bg-rose-950" @click="open('reject', u)">Tolak</button>
              </template>
              <template v-else-if="u.status === 'rejected'">
                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700" @click="open('approve', u)">Setujui</button>
              </template>
              <template v-else-if="u.status === 'active'">
                <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200" @click="open('access', u)">Atur akses</button>
                <button v-if="!u.is_self" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50 dark:ring-rose-900 dark:hover:bg-rose-950" @click="open('deactivate', u)">Nonaktifkan</button>
              </template>
              <template v-else-if="u.status === 'inactive'">
                <button type="button" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700" @click="activate(u)">Aktifkan</button>
              </template>
              <button v-if="!u.is_self" type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200" @click="open('message', u)">✉ Pesan</button>
            </div>
          </div>

          <p v-if="u.status_note && (u.status === 'rejected' || u.status === 'inactive')" class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
            Catatan: {{ u.status_note }}
          </p>
          <p v-if="u.status === 'active' && u.role === 'staff'" class="mt-3 text-xs text-slate-400">
            Hak akses: <span v-if="u.permissions.length">{{ u.permissions.map((p) => permissionOptions.find((o) => o.name === p)?.label ?? p).join(', ') }}</span><span v-else>hanya melihat laporan</span>
          </p>
        </div>
      </div>

      <Pagination :links="users.links" />
    </div>

    <!-- Modal aksi -->
    <div v-if="modal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" @click.self="close" @keydown.esc="close">
      <form class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-6 shadow-2xl sm:rounded-2xl dark:bg-slate-800" @submit.prevent="submit">
        <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ titles[modal.mode] }}</h3>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ modal.user.name }} · {{ modal.user.email }}</p>

        <p v-if="form.errors.general" role="alert" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950 dark:text-rose-300">{{ form.errors.general }}</p>
        <div class="mt-4"><ErrorSummary :errors="form.errors" /></div>

        <!-- setujui / ubah akses -->
        <div v-if="modal.mode === 'approve' || modal.mode === 'access'" class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label :class="labelClass">Role</label>
              <select v-model="form.role" :class="inputClass">
                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </div>
            <div>
              <label :class="labelClass">Posisi</label>
              <select v-model="form.position" :class="inputClass" @change="onPositionChange">
                <option value="">— tanpa posisi —</option>
                <option v-for="p in positions" :key="p.key" :value="p.key">{{ p.label }}</option>
              </select>
            </div>
          </div>

          <div v-if="form.role === 'staff'">
            <div class="flex items-center justify-between">
              <label :class="labelClass">Hak akses (selain melihat laporan)</label>
              <button type="button" class="text-xs font-medium text-emerald-600 hover:underline" @click="applyPreset">Terapkan preset posisi</button>
            </div>
            <div class="mt-2 grid gap-1.5 rounded-lg border border-slate-100 p-3 dark:border-slate-700">
              <label v-for="o in permissionOptions" :key="o.name" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input v-model="form.permissions" type="checkbox" :value="o.name" class="rounded border-slate-300 text-emerald-600" />
                {{ o.label }}
              </label>
            </div>
          </div>
          <p v-else-if="form.role === 'admin'" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">
            Manager memiliki <strong>semua</strong> fitur, termasuk menyetujui pengguna dan backup.
          </p>
          <p v-else class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
            Viewer hanya bisa melihat data dan laporan.
          </p>

          <div v-if="modal.mode === 'approve'">
            <label :class="labelClass">Pesan untuk yang bersangkutan (opsional, ikut dikirim di email)</label>
            <textarea v-model="form.note" rows="3" maxlength="500" placeholder="mis. Selamat bergabung! Tolong lengkapi profilmu." :class="inputClass"></textarea>
          </div>
        </div>

        <!-- tolak / nonaktifkan -->
        <div v-else-if="modal.mode === 'reject' || modal.mode === 'deactivate'">
          <label :class="labelClass">{{ modal.mode === 'reject' ? 'Alasan penolakan (dikirim ke pendaftar)' : 'Alasan (opsional)' }}</label>
          <textarea v-model="form.reason" rows="3" maxlength="500" :required="modal.mode === 'reject'" :class="inputClass"></textarea>
          <p v-if="modal.mode === 'deactivate'" class="mt-2 text-xs text-slate-400">Akun langsung tidak bisa dipakai, termasuk yang sedang login. Data transaksinya tetap tersimpan.</p>
        </div>

        <!-- pesan -->
        <div v-else-if="modal.mode === 'message'" class="space-y-4">
          <div>
            <label :class="labelClass">Subjek</label>
            <input v-model="form.subject" type="text" maxlength="150" required :class="inputClass" />
          </div>
          <div>
            <label :class="labelClass">Pesan</label>
            <textarea v-model="form.message" rows="6" maxlength="3000" required placeholder="mis. Mulai bulan ini kamu bertugas di bagian pajak…" :class="inputClass"></textarea>
            <p class="mt-1 text-right text-xs text-slate-400">{{ form.message.length }}/3000</p>
          </div>
          <p class="text-xs text-slate-400">Dikirim lewat Gmail sistem ke {{ modal.user.email }} dan tercatat di Activity Log.</p>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700" @click="close">Batal</button>
          <button type="submit" :disabled="form.processing" class="rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            :class="modal.mode === 'reject' || modal.mode === 'deactivate' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'">
            {{ form.processing ? 'Memproses…' : { approve: 'Setujui & kirim email', access: 'Simpan', reject: 'Tolak & kirim email', deactivate: 'Nonaktifkan', message: 'Kirim pesan' }[modal.mode] }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
