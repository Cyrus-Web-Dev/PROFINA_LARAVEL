<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({ trainings: Array })

const showForm = ref(false)
const form = useForm({ title: '', description: '', trainer: '', provider: '', start_date: '', end_date: '', location: '', quota: '' })
function submit() {
  form.post(route('trainings.store'), { onSuccess: () => { showForm.value = false; form.reset() } })
}
const statusLabels = { scheduled: 'Terjadwal', ongoing: 'Berlangsung', completed: 'Selesai', cancelled: 'Dibatalkan' }
const statusBadge = { scheduled: 'badge-seal', ongoing: 'badge-green', completed: 'badge-slate', cancelled: 'badge-red' }
</script>

<template>
  <AppLayout>
    <Head title="Pelatihan" />
    <template #title>Pelatihan</template>
    <template #actions>
      <button @click="showForm = !showForm" class="btn-accent btn-sm">{{ showForm ? 'Batal' : '+ Jadwalkan Pelatihan' }}</button>
    </template>

    <form v-if="showForm" @submit.prevent="submit" class="card card-body mb-6 space-y-3">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div><label class="label">Judul</label><input v-model="form.title" class="input" required /></div>
        <div><label class="label">Penyelenggara</label><input v-model="form.provider" class="input" /></div>
        <div><label class="label">Pemateri</label><input v-model="form.trainer" class="input" /></div>
        <div><label class="label">Lokasi</label><input v-model="form.location" class="input" /></div>
        <div><label class="label">Tanggal Mulai</label><input v-model="form.start_date" type="date" class="input" required /></div>
        <div><label class="label">Tanggal Selesai</label><input v-model="form.end_date" type="date" class="input" required /></div>
        <div><label class="label">Kuota</label><input v-model.number="form.quota" type="number" min="1" class="input" /></div>
      </div>
      <div><label class="label">Deskripsi</label><textarea v-model="form.description" rows="2" class="input"></textarea></div>
      <button type="submit" class="btn-accent">Jadwalkan</button>
    </form>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Judul</th><th>Tanggal</th><th>Peserta</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="t in trainings" :key="t.id">
              <td class="font-medium">{{ t.title }}</td>
              <td class="num">{{ t.start_date }} — {{ t.end_date }}</td>
              <td class="num">{{ t.participants_count }}{{ t.quota ? ' / ' + t.quota : '' }}</td>
              <td><span :class="statusBadge[t.status]">{{ statusLabels[t.status] }}</span></td>
              <td class="text-right"><Link :href="route('trainings.show', t.id)" class="btn-ghost btn-sm">Kelola</Link></td>
            </tr>
            <tr v-if="!trainings.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Belum ada pelatihan dijadwalkan.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
