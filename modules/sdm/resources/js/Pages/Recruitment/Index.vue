<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({ vacancies: Array })

const showForm = ref(false)
const form = useForm({ title: '', department_id: '', employment_type: 'kontrak', description: '', requirements: '', slots: 1, opens_at: '', closes_at: '' })
function submit() {
  form.post(route('recruitment.store'), { onSuccess: () => { showForm.value = false; form.reset() } })
}
const statusBadge = { open: 'badge-green', closed: 'badge-slate' }
</script>

<template>
  <AppLayout>
    <Head title="Rekrutmen" />
    <template #title>Rekrutmen</template>
    <template #actions>
      <button @click="showForm = !showForm" class="btn-accent btn-sm">{{ showForm ? 'Batal' : '+ Buka Lowongan' }}</button>
    </template>

    <form v-if="showForm" @submit.prevent="submit" class="card card-body mb-6 space-y-3">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div><label class="label">Judul Lowongan</label><input v-model="form.title" class="input" required /></div>
        <div><label class="label">Tipe Kepegawaian</label>
          <select v-model="form.employment_type" class="input">
            <option value="tetap">Tetap</option><option value="kontrak">Kontrak</option><option value="magang">Magang</option><option value="harian">Harian</option>
          </select>
        </div>
        <div><label class="label">Jumlah Slot</label><input v-model.number="form.slots" type="number" min="1" class="input" /></div>
        <div><label class="label">Batas Lamar</label><input v-model="form.closes_at" type="date" class="input" /></div>
      </div>
      <div><label class="label">Deskripsi Pekerjaan</label><textarea v-model="form.description" rows="3" class="input" required></textarea></div>
      <div><label class="label">Kualifikasi</label><textarea v-model="form.requirements" rows="3" class="input" required></textarea></div>
      <button type="submit" class="btn-accent">Publikasikan</button>
    </form>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Judul</th><th>Departemen</th><th>Pelamar</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="v in vacancies" :key="v.id">
              <td class="font-medium">{{ v.title }}</td>
              <td>{{ v.department?.name || '-' }}</td>
              <td class="num">{{ v.applicants_count }}</td>
              <td><span :class="statusBadge[v.status]">{{ v.status === 'open' ? 'Terbuka' : 'Ditutup' }}</span></td>
              <td class="text-right"><Link :href="route('recruitment.show', v.id)" class="btn-ghost btn-sm">Kelola</Link></td>
            </tr>
            <tr v-if="!vacancies.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Belum ada lowongan.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
