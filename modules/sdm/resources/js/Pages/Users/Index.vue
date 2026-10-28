<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router, usePage } from '@inertiajs/vue3'

const props = defineProps({ users: Object, roles: Array, unlinkedEmployees: Array, filters: Object })
const page = usePage()
const iAmSuper = computed(() => (page.props.auth.user.roles ?? []).includes('super-admin'))
const myId = computed(() => page.props.auth.user.id)

const statusBadge = { active: 'badge-green', pending: 'badge-amber', inactive: 'badge-slate', rejected: 'badge-red' }
const statusLabel = { active: 'Aktif', pending: 'Menunggu', inactive: 'Nonaktif', rejected: 'Ditolak' }
const tabs = [[null, 'Semua'], ['pending', 'Menunggu'], ['active', 'Aktif'], ['inactive', 'Nonaktif'], ['rejected', 'Ditolak']]

function filterStatus(status) {
  router.get(route('users.index'), status ? { status } : {}, { preserveState: true })
}
function reject(user) {
  const note = prompt('Alasan penolakan (opsional, akan dikirim ke email pendaftar):')
  if (note === null) return
  router.post(route('users.reject', user.id), { status_note: note || '' })
}
function toggleActive(user) {
  router.post(route('users.toggle-active', user.id), {}, { preserveScroll: true })
}
const canEditRoles = (u) => u.id !== myId.value && (iAmSuper.value || !u.roles.includes('super-admin'))

// --- Dialog persetujuan / hubungkan karyawan ---
const dialog = ref(null) // { mode: 'approve' | 'link', user }
const employeeId = ref('')
const role = ref('karyawan')

function openApprove(user) {
  const match = props.unlinkedEmployees.find(e => user.registration_nip && e.nip.toLowerCase() === user.registration_nip.toLowerCase())
  employeeId.value = match ? match.id : ''
  role.value = 'karyawan'
  dialog.value = { mode: 'approve', user, matched: !!match }
}
function openLink(user) {
  employeeId.value = user.employee_id ?? ''
  dialog.value = { mode: 'link', user }
}
const employeeOptions = computed(() => {
  const list = [...props.unlinkedEmployees]
  const u = dialog.value?.user
  if (u?.employee_id && !list.find(e => e.id === u.employee_id)) list.unshift({ id: u.employee_id, full_name: u.employee_name, nip: '(saat ini)' })
  return list
})
function submitDialog() {
  const { mode, user } = dialog.value
  const payload = { employee_id: employeeId.value || null }
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = null } }
  if (mode === 'approve') router.post(route('users.approve', user.id), { ...payload, role: role.value }, opts)
  else router.put(route('users.link-employee', user.id), payload, opts)
}

// --- Dialog role ---
const editingRoles = ref(null)
const selectedRoles = ref([])
function openRoles(user) { editingRoles.value = user; selectedRoles.value = [...user.roles] }
function saveRoles() {
  router.put(route('users.roles', editingRoles.value.id), { roles: selectedRoles.value }, { preserveScroll: true, onSuccess: () => { editingRoles.value = null } })
}
</script>

<template>
  <AppLayout>
    <Head title="Kelola Pengguna" />
    <template #title>Kelola Pengguna</template>

    <div class="mb-4 flex flex-wrap gap-2">
      <button v-for="[value, label] in tabs" :key="label" @click="filterStatus(value)" class="btn-secondary btn-sm"
        :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': (filters.status ?? null) === value }">{{ label }}</button>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Nama</th><th>Email</th><th>Karyawan Terhubung</th><th>Role</th><th>Status</th><th>Terdaftar</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="u in users.data" :key="u.id">
              <td class="font-medium">{{ u.name }}<p v-if="u.registration_nip" class="font-mono text-xs text-ink-400">NIP diisi: {{ u.registration_nip }}</p></td>
              <td class="text-ink-500">{{ u.email }}</td>
              <td>
                <span v-if="u.employee_name">{{ u.employee_name }}</span>
                <span v-else class="text-ink-400">—</span>
                <button v-if="u.status !== 'pending' && u.status !== 'rejected'" @click="openLink(u)" class="ml-1 text-xs text-seal-600 hover:underline dark:text-seal-400">{{ u.employee_name ? 'ubah' : 'hubungkan' }}</button>
              </td>
              <td>
                <span v-for="r in u.roles" :key="r" class="badge-seal mr-1">{{ r }}</span>
                <button v-if="u.status !== 'pending' && canEditRoles(u)" @click="openRoles(u)" class="text-xs text-seal-600 hover:underline dark:text-seal-400">ubah</button>
              </td>
              <td><span :class="statusBadge[u.status]">{{ statusLabel[u.status] }}</span></td>
              <td class="text-xs text-ink-400">{{ u.created_at }}</td>
              <td class="text-right">
                <div class="flex justify-end gap-2">
                  <button v-if="u.status === 'pending'" @click="openApprove(u)" class="btn-secondary btn-sm text-emerald-700">Tinjau &amp; Setujui</button>
                  <button v-if="u.status === 'pending'" @click="reject(u)" class="btn-ghost btn-sm text-red-600">Tolak</button>
                  <button v-if="(u.status === 'active' || u.status === 'inactive') && u.id !== myId && (iAmSuper || !u.roles.includes('super-admin'))" @click="toggleActive(u)" class="btn-ghost btn-sm">
                    {{ u.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!users.data.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Tidak ada pengguna.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="card-body"><Pagination :links="users.links" /></div>
    </div>

    <!-- Dialog: setujui pendaftar / hubungkan karyawan -->
    <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <form @submit.prevent="submitDialog" class="card w-full max-w-md">
        <div class="card-header"><h3 class="font-serif font-semibold">{{ dialog.mode === 'approve' ? 'Setujui Pendaftar' : 'Hubungkan ke Data Karyawan' }}: {{ dialog.user.name }}</h3></div>
        <div class="card-body space-y-4">
          <p v-if="dialog.mode === 'approve' && dialog.user.registration_nip" class="rounded-md px-3 py-2 text-xs" :class="dialog.matched ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300'">
            {{ dialog.matched ? 'NIP yang diisi pendaftar cocok dengan satu data karyawan (sudah dipilih otomatis). Pastikan orangnya benar.' : `NIP "${dialog.user.registration_nip}" tidak ditemukan di karyawan yang belum punya akun. Pilih manual bila ada.` }}
          </p>
          <div>
            <label class="label">Data Karyawan</label>
            <select v-model="employeeId" class="input">
              <option value="">— Belum dihubungkan —</option>
              <option v-for="e in employeeOptions" :key="e.id" :value="e.id">{{ e.full_name }} ({{ e.nip }})</option>
            </select>
            <p class="mt-1 text-xs text-ink-400">Tanpa hubungan ini, pengguna tidak bisa memakai absen, cuti, dan slip gaji.</p>
          </div>
          <div v-if="dialog.mode === 'approve'">
            <label class="label">Role</label>
            <select v-model="role" class="input"><option v-for="r in roles" :key="r" :value="r">{{ r }}</option></select>
          </div>
        </div>
        <div class="card-body flex gap-2 pt-0">
          <button type="submit" class="btn-accent btn-sm">{{ dialog.mode === 'approve' ? 'Setujui Akun' : 'Simpan' }}</button>
          <button type="button" @click="dialog = null" class="btn-secondary btn-sm">Batal</button>
        </div>
      </form>
    </div>

    <!-- Dialog: ubah role -->
    <div v-if="editingRoles" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="card w-full max-w-sm">
        <div class="card-header"><h3 class="font-serif font-semibold">Ubah Role: {{ editingRoles.name }}</h3></div>
        <div class="card-body space-y-2">
          <label v-for="r in roles" :key="r" class="flex items-center gap-2 text-sm">
            <input type="checkbox" :value="r" v-model="selectedRoles" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" />
            {{ r }}
          </label>
        </div>
        <div class="card-body flex gap-2 pt-0">
          <button @click="saveRoles" class="btn-accent btn-sm">Simpan</button>
          <button @click="editingRoles = null" class="btn-secondary btn-sm">Batal</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
