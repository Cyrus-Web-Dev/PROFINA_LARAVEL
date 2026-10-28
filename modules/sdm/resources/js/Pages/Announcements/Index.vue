<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'

defineProps({ announcements: Object })
const page = usePage()
const canManage = () => (page.props.auth.user.permissions ?? []).includes('announcements.manage')

const showForm = ref(false)
const form = useForm({ title: '', body: '', is_pinned: false })
function submit() {
  form.post(route('announcements.store'), { onSuccess: () => { showForm.value = false; form.reset() } })
}
function destroy(a) {
  if (confirm('Hapus pengumuman ini?')) router.delete(route('announcements.destroy', a.id))
}
function fmt(d) { return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-' }
</script>

<template>
  <AppLayout>
    <Head title="Pengumuman" />
    <template #title>Pengumuman</template>
    <template #actions>
      <button v-if="canManage()" @click="showForm = !showForm" class="btn-accent btn-sm">{{ showForm ? 'Batal' : '+ Buat Pengumuman' }}</button>
    </template>

    <form v-if="showForm" @submit.prevent="submit" class="card card-body mb-6 space-y-3">
      <div><label class="label">Judul</label><input v-model="form.title" class="input" required /></div>
      <div><label class="label">Isi</label><textarea v-model="form.body" rows="4" class="input" required></textarea></div>
      <label class="flex items-center gap-2 text-sm"><input v-model="form.is_pinned" type="checkbox" class="rounded border-ink-300 text-seal-600" /> Sematkan di atas</label>
      <button type="submit" class="btn-accent">Publikasikan</button>
    </form>

    <div class="space-y-4">
      <div v-for="a in announcements.data" :key="a.id" class="card card-body">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="font-serif font-semibold text-ink-900 dark:text-ink-100">
              <span v-if="a.is_pinned" class="badge-seal mr-2 align-middle">Disematkan</span>{{ a.title }}
            </h3>
            <p class="mt-1 text-xs text-ink-400">{{ fmt(a.published_at) }} &middot; {{ a.publisher?.name }}</p>
          </div>
          <button v-if="canManage()" @click="destroy(a)" class="btn-ghost btn-sm text-red-600">Hapus</button>
        </div>
        <p class="mt-3 text-sm whitespace-pre-line text-ink-700 dark:text-ink-300">{{ a.body }}</p>
      </div>
      <p v-if="!announcements.data.length" class="text-center text-sm text-ink-400 py-10">Belum ada pengumuman.</p>
    </div>
    <Pagination :links="announcements.links" />
  </AppLayout>
</template>
