<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({ cycles: Array, currentCycle: Object, reviews: Array, employeesWithoutReview: Array })

function selectCycle(id) {
  router.get(route('performance.index'), { cycle_id: id }, { preserveState: true })
}

const showCycleForm = ref(false)
const cycleForm = useForm({ name: '', start_date: '', end_date: '' })
function submitCycle() {
  cycleForm.post(route('performance.cycles.store'), { onSuccess: () => { showCycleForm.value = false; cycleForm.reset() } })
}

const showReviewForm = ref(false)
const reviewForm = useForm({ cycle_id: props.currentCycle?.id, employee_id: '', final_score: '', rating: '', strengths: '', improvements: '' })
function openReview(employeeId) {
  reviewForm.cycle_id = props.currentCycle.id
  reviewForm.employee_id = employeeId
  showReviewForm.value = true
}
function submitReview() {
  reviewForm.post(route('performance.store'), { onSuccess: () => { showReviewForm.value = false; reviewForm.reset('employee_id', 'final_score', 'rating', 'strengths', 'improvements') } })
}

const ratingBadge = { A: 'badge-green', B: 'badge-green', C: 'badge-amber', D: 'badge-red', E: 'badge-red' }
</script>

<template>
  <AppLayout>
    <Head title="Kinerja / KPI" />
    <template #title>Penilaian Kinerja</template>
    <template #actions>
      <button @click="showCycleForm = !showCycleForm" class="btn-secondary btn-sm">+ Siklus Baru</button>
    </template>

    <form v-if="showCycleForm" @submit.prevent="submitCycle" class="card card-body mb-6 flex flex-wrap items-end gap-4">
      <div><label class="label">Nama Siklus</label><input v-model="cycleForm.name" placeholder="Semester 1 2026" class="input" required /></div>
      <div><label class="label">Mulai</label><input v-model="cycleForm.start_date" type="date" class="input" required /></div>
      <div><label class="label">Selesai</label><input v-model="cycleForm.end_date" type="date" class="input" required /></div>
      <button type="submit" class="btn-accent">Buat</button>
    </form>

    <div class="mb-4 flex flex-wrap gap-2">
      <button v-for="c in cycles" :key="c.id" @click="selectCycle(c.id)" class="btn-secondary btn-sm" :class="{ '!bg-ink-900 !text-white dark:!bg-seal-500 dark:!text-ink-950': currentCycle?.id === c.id }">{{ c.name }}</button>
    </div>

    <div v-if="currentCycle" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="font-serif font-semibold">Hasil Penilaian — {{ currentCycle.name }}</h2></div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Karyawan</th><th>Skor</th><th>Rating</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="r in reviews" :key="r.id">
                <td class="font-medium">{{ r.employee.full_name }}</td>
                <td class="num">{{ r.final_score ?? '-' }}</td>
                <td><span v-if="r.rating" :class="ratingBadge[r.rating]">{{ r.rating }}</span></td>
                <td class="text-ink-500 text-xs">{{ r.status }}</td>
              </tr>
              <tr v-if="!reviews.length"><td colspan="4" class="py-8 text-center text-sm text-ink-400">Belum ada penilaian di siklus ini.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="font-serif font-semibold text-sm">Belum Dinilai</h2></div>
        <ul class="card-body space-y-2">
          <li v-for="e in employeesWithoutReview" :key="e.id" class="flex items-center justify-between text-sm">
            <span>{{ e.full_name }}</span>
            <button @click="openReview(e.id)" class="btn-ghost btn-sm">Nilai</button>
          </li>
          <li v-if="!employeesWithoutReview.length" class="text-sm text-ink-400">Semua karyawan sudah dinilai.</li>
        </ul>
      </div>
    </div>
    <p v-else class="text-sm text-ink-400">Belum ada siklus penilaian. Buat siklus baru untuk mulai.</p>

    <div v-if="showReviewForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
      <form @submit.prevent="submitReview" class="card w-full max-w-lg my-8">
        <div class="card-header"><h3 class="font-serif font-semibold">Beri Penilaian</h3></div>
        <div class="card-body space-y-3">
          <div><label class="label">Skor Akhir (0-100)</label><input v-model.number="reviewForm.final_score" type="number" min="0" max="100" class="input" /></div>
          <div><label class="label">Rating</label>
            <select v-model="reviewForm.rating" class="input"><option value="">—</option><option v-for="r in ['A','B','C','D','E']" :key="r" :value="r">{{ r }}</option></select>
          </div>
          <div><label class="label">Kekuatan</label><textarea v-model="reviewForm.strengths" rows="2" class="input"></textarea></div>
          <div><label class="label">Area Pengembangan</label><textarea v-model="reviewForm.improvements" rows="2" class="input"></textarea></div>
        </div>
        <div class="card-body pt-0 flex gap-2">
          <button type="submit" class="btn-accent btn-sm">Simpan Penilaian</button>
          <button type="button" @click="showReviewForm = false" class="btn-secondary btn-sm">Batal</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
