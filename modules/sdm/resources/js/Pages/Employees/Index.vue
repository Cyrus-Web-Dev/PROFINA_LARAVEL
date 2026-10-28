<script setup>
import { ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  employees: Object,
  departments: Array,
  filters: Object,
})

const page = usePage()
const canManage = () => (page.props.auth.user.permissions ?? []).includes('employees.manage')
const canExport = () => (page.props.auth.user.permissions ?? []).includes('employees.export')

const search = ref(props.filters.search ?? '')
const departmentId = ref(props.filters.department_id ?? '')
const employmentStatus = ref(props.filters.employment_status ?? '')

let debounceTimer = null
watch([search, departmentId, employmentStatus], () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(route('employees.index'), {
      search: search.value || undefined,
      department_id: departmentId.value || undefined,
      employment_status: employmentStatus.value || undefined,
    }, { preserveState: true, replace: true })
  }, 350)
})

const statusBadge = { active: 'badge-green', resigned: 'badge-slate', terminated: 'badge-red', retired: 'badge-slate' }

function destroy(employee) {
  if (confirm(`Arsipkan data ${employee.full_name}? Data tidak hilang permanen dan bisa dipulihkan lewat database.`)) {
    router.delete(route('employees.destroy', employee.id), { preserveScroll: true })
  }
}
</script>

<template>
  <AppLayout>
    <Head title="Karyawan" />
    <template #title>Karyawan</template>
    <template #actions>
      <a v-if="canExport()" :href="route('employees.export')" class="btn-secondary btn-sm">Ekspor Excel</a>
      <Link v-if="canManage()" :href="route('employees.create')" class="btn-accent btn-sm">+ Tambah Karyawan</Link>
    </template>

    <div class="card">
      <div class="card-header flex-col items-stretch gap-3 sm:flex-row sm:items-center">
        <input v-model="search" type="text" placeholder="Cari nama atau NIP…" class="input sm:max-w-xs" />
        <select v-model="departmentId" class="input sm:max-w-[200px]">
          <option value="">Semua Departemen</option>
          <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-model="employmentStatus" class="input sm:max-w-[180px]">
          <option value="">Semua Status</option>
          <option value="active">Aktif</option>
          <option value="resigned">Resign</option>
          <option value="terminated">Diberhentikan</option>
          <option value="retired">Pensiun</option>
        </select>
      </div>

      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Karyawan</th>
              <th>NIP</th>
              <th>Departemen / Jabatan</th>
              <th>Tipe</th>
              <th>Status</th>
              <th>Bergabung</th>
              <th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in employees.data" :key="e.id">
              <td>
                <Link :href="route('employees.show', e.id)" class="flex items-center gap-3 hover:underline">
                  <UserAvatar :src="e.photo_url" :name="e.full_name" :size="32" />
                  <span class="font-medium text-ink-900 dark:text-ink-100">{{ e.full_name }}</span>
                </Link>
              </td>
              <td class="num text-ink-500">{{ e.nip }}</td>
              <td>
                <p class="text-ink-700 dark:text-ink-300">{{ e.department || '—' }}</p>
                <p class="text-xs text-ink-400">{{ e.position || '—' }}</p>
              </td>
              <td class="text-ink-600 dark:text-ink-300">{{ e.employment_type }}</td>
              <td><span :class="statusBadge[e.employment_status] ?? 'badge-slate'">{{ e.employment_status_label }}</span></td>
              <td class="num text-ink-500">{{ e.join_date }}</td>
              <td class="text-right">
                <div class="flex justify-end gap-2">
                  <Link :href="route('employees.show', e.id)" class="btn-ghost btn-sm">Lihat</Link>
                  <Link v-if="canManage()" :href="route('employees.edit', e.id)" class="btn-ghost btn-sm">Ubah</Link>
                  <button v-if="canManage()" @click="destroy(e)" class="btn-ghost btn-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950">Arsip</button>
                </div>
              </td>
            </tr>
            <tr v-if="!employees.data.length">
              <td colspan="7" class="py-10 text-center text-sm text-ink-400">Belum ada data karyawan.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="card-body">
        <Pagination :links="employees.links" />
      </div>
    </div>
  </AppLayout>
</template>
