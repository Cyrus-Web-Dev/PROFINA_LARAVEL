<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'

const props = defineProps({ departments: Array, positions: Array })
const page = usePage()
const canManage = () => (page.props.auth.user.permissions ?? []).includes('departments.manage')

const showDeptForm = ref(false)
const editingDept = ref(null)
const deptForm = useForm({ code: '', name: '', manager_employee_id: '', description: '' })

function openCreate() { editingDept.value = null; deptForm.reset(); showDeptForm.value = true }
function openEdit(d) {
  editingDept.value = d
  deptForm.code = d.code; deptForm.name = d.name; deptForm.manager_employee_id = d.manager?.id ?? ''; deptForm.description = d.description ?? ''
  showDeptForm.value = true
}
function submitDept() {
  if (editingDept.value) {
    deptForm.put(route('departments.update', editingDept.value.id), { onSuccess: () => { showDeptForm.value = false } })
  } else {
    deptForm.post(route('departments.store'), { onSuccess: () => { showDeptForm.value = false; deptForm.reset() } })
  }
}
function destroyDept(d) {
  if (confirm(`Hapus departemen ${d.name}?`)) router.delete(route('departments.destroy', d.id))
}

const showPosForm = ref(false)
const posForm = useForm({ department_id: '', title: '', level: 5 })
function submitPos() {
  posForm.post(route('positions.store'), { onSuccess: () => { posForm.reset(); showPosForm.value = false } })
}
</script>

<template>
  <AppLayout>
    <Head title="Departemen" />
    <template #title>Departemen &amp; Jabatan</template>
    <template #actions>
      <button v-if="canManage()" @click="openCreate" class="btn-accent btn-sm">+ Departemen</button>
    </template>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="font-serif font-semibold">Daftar Departemen</h2></div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Kode</th><th>Nama</th><th>Manajer</th><th>Jml. Karyawan</th><th v-if="canManage()" class="text-right">Aksi</th></tr></thead>
            <tbody>
              <tr v-for="d in departments" :key="d.id">
                <td class="font-mono">{{ d.code }}</td>
                <td class="font-medium">{{ d.name }}</td>
                <td>{{ d.manager?.full_name || '-' }}</td>
                <td class="num">{{ d.employees_count }}</td>
                <td v-if="canManage()" class="text-right">
                  <div class="flex justify-end gap-2">
                    <button @click="openEdit(d)" class="btn-ghost btn-sm">Ubah</button>
                    <button @click="destroyDept(d)" class="btn-ghost btn-sm text-red-600">Hapus</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <form v-if="showDeptForm" @submit.prevent="submitDept" class="card-body space-y-3 border-t border-ink-100 dark:border-ink-800">
          <h3 class="font-medium text-sm">{{ editingDept ? 'Ubah Departemen' : 'Departemen Baru' }}</h3>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div><label class="label">Kode</label><input v-model="deptForm.code" class="input" required /></div>
            <div><label class="label">Nama</label><input v-model="deptForm.name" class="input" required /></div>
          </div>
          <div><label class="label">Deskripsi</label><textarea v-model="deptForm.description" rows="2" class="input"></textarea></div>
          <div class="flex gap-2">
            <button type="submit" class="btn-accent btn-sm">Simpan</button>
            <button type="button" @click="showDeptForm = false" class="btn-secondary btn-sm">Batal</button>
          </div>
        </form>
      </div>

      <div class="card">
        <div class="card-header">
          <h2 class="font-serif font-semibold">Jabatan</h2>
          <button v-if="canManage()" @click="showPosForm = !showPosForm" class="btn-secondary btn-sm">+ Tambah</button>
        </div>
        <form v-if="showPosForm" @submit.prevent="submitPos" class="card-body space-y-3 border-b border-ink-100 dark:border-ink-800">
          <div><label class="label">Departemen</label>
            <select v-model="posForm.department_id" class="input">
              <option value="">—</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div><label class="label">Nama Jabatan</label><input v-model="posForm.title" class="input" required /></div>
          <div><label class="label">Jenjang (1=tertinggi)</label><input v-model.number="posForm.level" type="number" min="1" max="10" class="input" /></div>
          <button type="submit" class="btn-accent btn-sm">Simpan</button>
        </form>
        <ul class="card-body space-y-2 max-h-96 overflow-y-auto">
          <li v-for="p in positions" :key="p.id" class="flex justify-between text-sm">
            <span>{{ p.title }}</span>
            <span class="text-ink-400 text-xs">{{ p.department?.name || '-' }}</span>
          </li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
