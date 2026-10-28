<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({ attendances: Array, date: String, departments: Array, filters: Object })

const selectedDate = ref(props.date)
function changeDate() {
  router.get(route('attendances.index'), { date: selectedDate.value, department_id: props.filters.department_id }, { preserveState: true })
}

const statusBadge = { hadir: 'badge-green', terlambat: 'badge-amber', izin: 'badge-slate', sakit: 'badge-slate', alpha: 'badge-red', cuti: 'badge-seal', wfh: 'badge-green', libur: 'badge-slate' }
const statusLabels = { hadir: 'Hadir', terlambat: 'Terlambat', izin: 'Izin', sakit: 'Sakit', alpha: 'Alpha', cuti: 'Cuti', wfh: 'WFH', libur: 'Libur' }

const editing = ref(null)
const form = useForm({ check_in: '', check_out: '', status: 'hadir', notes: '' })
function openEdit(a) {
  editing.value = a
  form.check_in = a.check_in?.slice(0, 5) ?? ''
  form.check_out = a.check_out?.slice(0, 5) ?? ''
  form.status = a.status
  form.notes = a.notes ?? ''
}
function save() {
  form.put(route('attendances.update', editing.value.id), { onSuccess: () => { editing.value = null } })
}
</script>

<template>
  <AppLayout>
    <Head title="Absensi" />
    <template #title>Absensi</template>

    <div class="card">
      <div class="card-header">
        <input v-model="selectedDate" type="date" class="input max-w-[180px]" @change="changeDate" />
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Karyawan</th><th>NIP</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Terlambat</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="a in attendances" :key="a.id">
              <td class="font-medium">{{ a.employee.full_name }}</td>
              <td class="font-mono text-ink-500">{{ a.employee.nip }}</td>
              <td class="font-mono">{{ a.check_in?.slice(0,5) || '-' }}</td>
              <td class="font-mono">{{ a.check_out?.slice(0,5) || '-' }}</td>
              <td><span :class="statusBadge[a.status]">{{ statusLabels[a.status] }}</span></td>
              <td class="num">{{ a.late_minutes > 0 ? a.late_minutes + ' mnt' : '-' }}</td>
              <td class="text-right"><button @click="openEdit(a)" class="btn-ghost btn-sm">Koreksi</button></td>
            </tr>
            <tr v-if="!attendances.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Belum ada data absensi tanggal ini.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <form @submit.prevent="save" class="card w-full max-w-sm">
        <div class="card-header"><h3 class="font-serif font-semibold">Koreksi: {{ editing.employee.full_name }}</h3></div>
        <div class="card-body space-y-3">
          <div><label class="label">Check-in</label><input v-model="form.check_in" type="time" class="input" /></div>
          <div><label class="label">Check-out</label><input v-model="form.check_out" type="time" class="input" /></div>
          <div><label class="label">Status</label>
            <select v-model="form.status" class="input">
              <option v-for="(l, k) in statusLabels" :key="k" :value="k">{{ l }}</option>
            </select>
          </div>
          <div><label class="label">Catatan</label><textarea v-model="form.notes" rows="2" class="input"></textarea></div>
        </div>
        <div class="card-body pt-0 flex gap-2">
          <button type="submit" class="btn-accent btn-sm">Simpan</button>
          <button type="button" @click="editing = null" class="btn-secondary btn-sm">Batal</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
