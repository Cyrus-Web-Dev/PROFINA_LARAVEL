<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { reactive, ref, computed } from 'vue'

const props = defineProps({ users: Object, filters: Object, catalog: Array, coreRoles: Array })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)

const f = reactive({ q: props.filters.q ?? '', status: props.filters.status ?? '' })
const search = () => router.get(route('admin.users.index'), { ...f }, { preserveState: true, replace: true })

const statusBadge = { active: 'badge-green', pending: 'badge-amber', inactive: 'badge-slate', rejected: 'badge-red' }
const statusLabel = { active: 'Aktif', pending: 'Menunggu', inactive: 'Nonaktif', rejected: 'Ditolak' }
const moduleName = (code) => props.catalog.find((m) => m.code === code)?.name ?? code
const roleLabel = (code, role) => props.catalog.find((m) => m.code === code)?.roles.find((r) => r.value === role)?.label ?? role

// ---- Tambah akun ----
const showCreate = ref(false)
const createForm = useForm({ name: '', email: '', registration_nip: '' })
const submitCreate = () => createForm.post(route('admin.users.store'), { preserveScroll: true, onSuccess: () => { createForm.reset(); showCreate.value = false } })

// ---- Penugasan ----
const editing = ref(null)
const accessForm = useForm({ core_role: '', assignments: [] })
function openAccess(u) {
  editing.value = u
  accessForm.core_role = u.core_role ?? ''
  accessForm.assignments = u.assignments.map((a) => ({ module: a.module, role: a.role, permissions: a.permissions ? [...a.permissions] : null }))
  accessForm.clearErrors()
}
const assigned = (code) => accessForm.assignments.find((a) => a.module === code)
function toggleModule(m) {
  const idx = accessForm.assignments.findIndex((a) => a.module === m.code)
  if (idx >= 0) accessForm.assignments.splice(idx, 1)
  else accessForm.assignments.push({ module: m.code, role: m.roles[0].value, permissions: m.permissions.length ? [] : null })
}
function togglePerm(a, p) {
  const i = a.permissions.indexOf(p)
  if (i >= 0) a.permissions.splice(i, 1)
  else a.permissions.push(p)
}
const needsPerms = (m, a) => m.permissions.length && a && a.role === 'staff'
function saveAccess() {
  accessForm
    .transform((d) => ({
      core_role: d.core_role || null,
      assignments: d.assignments.map((a) => {
        const m = props.catalog.find((x) => x.code === a.module)
        return { module: a.module, role: a.role, permissions: needsPerms(m, a) ? a.permissions : null }
      }),
    }))
    .put(route('admin.users.access', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) })
}

// ---- Aksi cepat ----
const post = (name, u, data = {}) => router.post(route(name, u.id), data, { preserveScroll: true })
function reject(u) { const note = prompt('Alasan penolakan (opsional):'); if (note !== null) post('admin.users.reject', u, { note }) }
function toggle(u) { if (confirm(`${u.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'} ${u.name}?`)) post('admin.users.toggle-active', u) }
function resetPw(u) { if (confirm(`Reset password ${u.name}? Semua sesi modulnya dicabut.`)) post('admin.users.reset-password', u) }
</script>

<template>
  <AppLayout>
    <Head title="Pengguna & Penugasan" />
    <template #title>Pengguna & Penugasan Modul</template>
    <template #actions><button v-if="can('users.manage')" class="btn-accent btn-sm" @click="showCreate = true">+ Akun baru</button></template>

    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
      <input v-model="f.q" class="input max-w-xs" placeholder="Cari nama / email…" />
      <select v-model="f.status" class="input max-w-[11rem]" @change="search">
        <option value="">Semua status</option><option value="pending">Menunggu</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option><option value="rejected">Ditolak</option>
      </select>
      <button class="btn-secondary">Cari</button>
    </form>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Pengguna</th><th>Status</th><th>Peran Portal</th><th>Penugasan modul</th><th>Login terakhir</th><th></th></tr></thead>
        <tbody>
          <tr v-for="u in users.data" :key="u.id">
            <td>{{ u.name }}<br><span class="text-xs text-ink-500">{{ u.email }}<template v-if="u.nip"> · NIP {{ u.nip }}</template></span></td>
            <td><span :class="statusBadge[u.status] ?? 'badge-slate'">{{ statusLabel[u.status] ?? u.status }}</span><p v-if="u.status_note" class="mt-1 text-xs text-ink-500">{{ u.status_note }}</p></td>
            <td><span v-if="u.core_role" class="badge-seal">{{ u.core_role }}</span><span v-else class="text-ink-400">—</span></td>
            <td>
              <div class="flex flex-wrap gap-1">
                <span v-for="a in u.assignments" :key="a.module" class="badge-slate" :title="roleLabel(a.module, a.role)">{{ moduleName(a.module) }}: {{ a.role }}</span>
                <span v-if="!u.assignments.length" class="text-ink-400">belum ada</span>
              </div>
            </td>
            <td class="font-mono text-xs">{{ u.last_login_at || '—' }}</td>
            <td class="whitespace-nowrap text-right">
              <template v-if="can('users.manage')">
                <template v-if="u.status === 'pending'">
                  <button class="btn-primary btn-sm" @click="post('admin.users.approve', u)">Setujui</button>
                  <button class="btn-ghost btn-sm text-red-600" @click="reject(u)">Tolak</button>
                </template>
                <template v-else>
                  <button class="btn-secondary btn-sm" @click="openAccess(u)">Penugasan</button>
                  <button class="btn-ghost btn-sm" @click="resetPw(u)">Reset PW</button>
                  <button class="btn-ghost btn-sm" :class="u.status === 'active' ? 'text-red-600' : ''" @click="toggle(u)">{{ u.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                </template>
              </template>
            </td>
          </tr>
          <tr v-if="!users.data.length"><td colspan="6" class="py-6 text-center text-ink-500">Tidak ada pengguna.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="users.links" />

    <!-- Modal akun baru -->
    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreate = false">
      <form class="card card-body w-full max-w-md space-y-4" @submit.prevent="submitCreate">
        <h2 class="font-serif text-lg font-semibold">Akun baru</h2>
        <div><label class="label">Nama</label><input v-model="createForm.name" class="input" required /><p class="error-text">{{ createForm.errors.name }}</p></div>
        <div><label class="label">Email</label><input v-model="createForm.email" type="email" class="input" required /><p class="error-text">{{ createForm.errors.email }}</p></div>
        <div><label class="label">NIP (opsional — dipakai SDM untuk menautkan data karyawan)</label><input v-model="createForm.registration_nip" class="input" /></div>
        <p class="text-xs text-ink-500">Password sementara dikirim ke email; user wajib menggantinya saat login pertama.</p>
        <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="showCreate = false">Batal</button><button class="btn-accent" :disabled="createForm.processing">Buat akun</button></div>
      </form>
    </div>

    <!-- Modal penugasan -->
    <div v-if="editing" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @click.self="editing = null">
      <form class="card card-body my-8 w-full max-w-2xl space-y-5" @submit.prevent="saveAccess">
        <div>
          <h2 class="font-serif text-lg font-semibold">Penugasan — {{ editing.name }}</h2>
          <p class="text-xs text-ink-500">{{ editing.email }}. Satu user boleh punya beberapa modul, tetapi hanya SATU role per modul. Modul yang tidak dicentang = tidak bisa dimasuki.</p>
        </div>

        <div v-for="m in catalog" :key="m.code" class="rounded-md border p-3" :class="assigned(m.code) ? 'border-seal-400 bg-seal-50/40 dark:bg-seal-900/10' : 'border-ink-200 dark:border-ink-800'">
          <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" class="mt-1 rounded border-ink-300 text-seal-600" :checked="!!assigned(m.code)" @change="toggleModule(m)" />
            <span><span class="font-medium">{{ m.name }}</span><br><span class="text-xs text-ink-500">{{ m.description }}</span></span>
          </label>
          <div v-if="assigned(m.code)" class="mt-3 pl-7">
            <label class="label">Role di modul ini</label>
            <select v-model="assigned(m.code).role" class="input"><option v-for="r in m.roles" :key="r.value" :value="r.value">{{ r.label }}</option></select>
            <div v-if="needsPerms(m, assigned(m.code))" class="mt-3">
              <p class="label">Hak tambahan per orang (role Staff)</p>
              <div class="grid gap-1 sm:grid-cols-2">
                <label v-for="p in m.permissions" :key="p.value" class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-ink-300 text-seal-600" :checked="assigned(m.code).permissions.includes(p.value)" @change="togglePerm(assigned(m.code), p.value)" />{{ p.label }}</label>
              </div>
            </div>
          </div>
        </div>

        <div v-if="can('users.manage')" class="rounded-md border border-ink-200 p-3 dark:border-ink-800">
          <label class="label">Peran di Portal (modul Inti)</label>
          <select v-model="accessForm.core_role" class="input"><option value="">— tidak ada akses Inti —</option><option v-for="r in coreRoles" :key="r.value" :value="r.value">{{ r.label }}</option></select>
        </div>

        <p v-for="(msg, k) in accessForm.errors" :key="k" class="error-text">{{ msg }}</p>
        <div class="flex justify-end gap-2"><button type="button" class="btn-ghost" @click="editing = null">Batal</button><button class="btn-accent" :disabled="accessForm.processing">Simpan penugasan</button></div>
      </form>
    </div>
  </AppLayout>
</template>
