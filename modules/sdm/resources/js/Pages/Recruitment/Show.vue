<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ vacancy: Object, applicants: Array })

const stages = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected']
const stageLabels = { applied: 'Melamar', screening: 'Seleksi Admin', interview: 'Wawancara', offer: 'Penawaran', hired: 'Diterima', rejected: 'Ditolak' }

function updateStage(applicant, stage) {
  router.put(route('recruitment.applicants.stage', applicant.id), { stage })
}
function toggleVacancyStatus() {
  router.put(route('recruitment.update', props.vacancy.id), { status: props.vacancy.status === 'open' ? 'closed' : 'open', slots: props.vacancy.slots })
}
</script>

<template>
  <AppLayout>
    <Head :title="vacancy.title" />
    <template #title>{{ vacancy.title }}</template>
    <template #actions>
      <button @click="toggleVacancyStatus" class="btn-secondary btn-sm">{{ vacancy.status === 'open' ? 'Tutup Lowongan' : 'Buka Kembali' }}</button>
    </template>

    <div class="card mb-6">
      <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
        <div><p class="text-ink-400">Departemen</p><p class="font-medium">{{ vacancy.department?.name || '-' }}</p></div>
        <div><p class="text-ink-400">Slot</p><p class="font-medium">{{ vacancy.slots }}</p></div>
        <div class="sm:col-span-2"><p class="text-ink-400">Deskripsi</p><p class="whitespace-pre-line">{{ vacancy.description }}</p></div>
        <div class="sm:col-span-2"><p class="text-ink-400">Kualifikasi</p><p class="whitespace-pre-line">{{ vacancy.requirements }}</p></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="font-serif font-semibold">Pelamar ({{ applicants.length }})</h2></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Nama</th><th>Kontak</th><th>Tahap</th><th>Resume</th></tr></thead>
          <tbody>
            <tr v-for="a in applicants" :key="a.id">
              <td class="font-medium">{{ a.full_name }}</td>
              <td class="text-ink-500 text-xs">{{ a.email }}<br/>{{ a.phone }}</td>
              <td>
                <select :value="a.stage" @change="updateStage(a, $event.target.value)" class="input !py-1 text-xs">
                  <option v-for="s in stages" :key="s" :value="s">{{ stageLabels[s] }}</option>
                </select>
              </td>
              <td><a :href="route('recruitment.applicants.resume', a.id)" class="text-seal-600 hover:underline dark:text-seal-400 text-xs">Lihat CV</a></td>
            </tr>
            <tr v-if="!applicants.length"><td colspan="4" class="py-10 text-center text-sm text-ink-400">Belum ada pelamar.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
