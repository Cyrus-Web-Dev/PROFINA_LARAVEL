<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ training: Object, participants: Array, availableEmployees: Array })

const selectedEmployee = ref('')
function addParticipant() {
  if (!selectedEmployee.value) return
  router.post(route('trainings.participants.store', props.training.id), { employee_id: selectedEmployee.value }, { onSuccess: () => { selectedEmployee.value = '' } })
}
function updateParticipant(p, status) {
  router.put(route('trainings.participants.update', p.id), { status })
}
const statusLabels = { registered: 'Terdaftar', attended: 'Hadir', absent: 'Absen', completed: 'Selesai' }
</script>

<template>
  <AppLayout>
    <Head :title="training.title" />
    <template #title>{{ training.title }}</template>

    <div class="card mb-6">
      <div class="card-body flex flex-wrap gap-2">
        <select v-model="selectedEmployee" class="input max-w-xs">
          <option value="">Pilih karyawan untuk ditambahkan…</option>
          <option v-for="e in availableEmployees" :key="e.id" :value="e.id">{{ e.full_name }} ({{ e.nip }})</option>
        </select>
        <button @click="addParticipant" class="btn-accent btn-sm">Tambah Peserta</button>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="font-serif font-semibold">Peserta ({{ participants.length }})</h2></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Karyawan</th><th>Status</th><th>Nilai</th></tr></thead>
          <tbody>
            <tr v-for="p in participants" :key="p.id">
              <td class="font-medium">{{ p.employee.full_name }}</td>
              <td>
                <select :value="p.status" @change="updateParticipant(p, $event.target.value)" class="input !py-1 text-xs">
                  <option v-for="(l,k) in statusLabels" :key="k" :value="k">{{ l }}</option>
                </select>
              </td>
              <td class="num">{{ p.score ?? '-' }}</td>
            </tr>
            <tr v-if="!participants.length"><td colspan="3" class="py-10 text-center text-sm text-ink-400">Belum ada peserta.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
