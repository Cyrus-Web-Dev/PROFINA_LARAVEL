<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Head, useForm, router } from '@inertiajs/vue3'

const props = defineProps({ profile: Object })

const infoForm = useForm({ name: props.profile.name })
function updateInfo() {
  infoForm.patch(route('profile.update'))
}

const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' })
function updatePassword() {
  passwordForm.put(route('profile.password'), {
    errorBag: 'updatePassword',
    onSuccess: () => passwordForm.reset(),
  })
}

function onPhotoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  router.post(route('profile.photo.upload'), { photo: file }, { forceFormData: true })
}
function deletePhoto() {
  if (confirm('Hapus foto profil?')) router.delete(route('profile.photo.delete'))
}
</script>

<template>
  <AppLayout>
    <Head title="Profil Saya" />
    <template #title>Profil Saya</template>

    <div class="max-w-2xl space-y-6">
      <div v-if="profile.must_change_password" class="rounded-md border border-seal-300 bg-seal-50 px-4 py-3 text-sm text-seal-900 dark:border-seal-700 dark:bg-seal-900/30 dark:text-seal-200">
        <strong>Wajib ganti password.</strong> Kamu masih memakai password sementara. Ganti dulu di bagian "Ganti Password" di bawah — menu lain baru bisa dibuka setelahnya.
      </div>
      <div class="card card-body flex items-center gap-5">
        <UserAvatar :src="profile.avatar_url" :name="profile.name" :size="72" />
        <div>
          <label class="btn-secondary btn-sm cursor-pointer">
            Ganti Foto
            <input type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
          </label>
          <button v-if="profile.avatar_url" @click="deletePhoto" class="btn-ghost btn-sm ml-2 text-red-600">Hapus</button>
        </div>
      </div>

      <div v-if="profile.assignments?.length" class="card card-body">
        <h2 class="text-sm font-semibold text-ink-900 dark:text-ink-100">Penugasan Modul</h2>
        <ul class="mt-3 divide-y divide-ink-100 text-sm dark:divide-ink-800">
          <li v-for="a in profile.assignments" :key="a.module" class="flex items-center justify-between py-2">
            <span>{{ a.module }}</span><span class="badge-slate">{{ a.role }}</span>
          </li>
        </ul>
      </div>

      <form @submit.prevent="updateInfo" class="card card-body space-y-4">
        <h2 class="font-serif font-semibold">Informasi Akun</h2>
        <div><label class="label">Nama</label><input v-model="infoForm.name" class="input" /></div>
        <div>
          <label class="label">Email</label>
          <input :value="profile.email" type="email" class="input bg-ink-50 dark:bg-ink-800" disabled />
          <p class="mt-1 text-xs text-ink-500">Email adalah identitas masukmu di semua modul. Email tidak dapat diubah sendiri; bila perlu ganti, minta Admin PROFINA membuat akun baru lalu memindahkan penugasannya.</p>
        </div>
        <button type="submit" :disabled="infoForm.processing" class="btn-accent">Simpan</button>
      </form>

      <form @submit.prevent="updatePassword" class="card card-body space-y-4">
        <h2 class="font-serif font-semibold">Ganti Password</h2>
        <div><label class="label">Password Saat Ini</label><input v-model="passwordForm.current_password" type="password" class="input" /><p v-if="passwordForm.errors.current_password" class="error-text">{{ passwordForm.errors.current_password }}</p></div>
        <div><label class="label">Password Baru</label><input v-model="passwordForm.password" type="password" class="input" /><p v-if="passwordForm.errors.password" class="error-text">{{ passwordForm.errors.password }}</p></div>
        <div><label class="label">Konfirmasi Password Baru</label><input v-model="passwordForm.password_confirmation" type="password" class="input" /></div>
        <button type="submit" :disabled="passwordForm.processing" class="btn-accent">Ganti Password</button>
      </form>
    </div>
  </AppLayout>
</template>
