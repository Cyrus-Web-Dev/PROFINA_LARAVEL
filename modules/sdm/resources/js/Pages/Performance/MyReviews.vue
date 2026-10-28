<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

defineProps({ reviews: Array })

const commentForm = useForm({ employee_comment: '' })
const activeId = ref(null)
function submitComment(review) {
  commentForm.post(route('performance.acknowledge', review.id), { onSuccess: () => { activeId.value = null } })
}
const ratingBadge = { A: 'badge-green', B: 'badge-green', C: 'badge-amber', D: 'badge-red', E: 'badge-red' }
</script>

<template>
  <AppLayout>
    <Head title="Penilaian Saya" />
    <template #title>Penilaian Kinerja Saya</template>

    <div class="space-y-4">
      <div v-for="r in reviews" :key="r.id" class="card card-body">
        <div class="flex items-center justify-between">
          <h3 class="font-serif font-semibold">{{ r.cycle.name }}</h3>
          <span v-if="r.rating" :class="ratingBadge[r.rating]">{{ r.rating }}</span>
        </div>
        <p class="mt-1 text-sm text-ink-500">Skor: {{ r.final_score ?? '-' }}</p>
        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
          <div><p class="text-ink-400">Kekuatan</p><p>{{ r.strengths || '-' }}</p></div>
          <div><p class="text-ink-400">Area Pengembangan</p><p>{{ r.improvements || '-' }}</p></div>
        </div>
        <div v-if="r.employee_comment" class="mt-3 rounded-md bg-ink-50 p-3 text-sm dark:bg-ink-800/50">
          <p class="text-xs text-ink-400">Tanggapanmu:</p>{{ r.employee_comment }}
        </div>
        <div v-else-if="r.status === 'submitted'" class="mt-3">
          <button v-if="activeId !== r.id" @click="activeId = r.id" class="btn-secondary btn-sm">Beri Tanggapan</button>
          <form v-else @submit.prevent="submitComment(r)" class="mt-2 space-y-2">
            <textarea v-model="commentForm.employee_comment" rows="2" class="input" placeholder="Tanggapanmu atas penilaian ini…"></textarea>
            <button type="submit" class="btn-accent btn-sm">Kirim</button>
          </form>
        </div>
      </div>
      <p v-if="!reviews.length" class="text-center text-sm text-ink-400 py-10">Belum ada penilaian kinerja.</p>
    </div>
  </AppLayout>
</template>
