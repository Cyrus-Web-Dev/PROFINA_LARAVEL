<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({ email: String, token: String })

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <GuestLayout>
    <Head title="Reset Password" />
    <h1 class="text-lg font-serif font-semibold text-ink-900 dark:text-ink-100">Atur Password Baru</h1>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="label">Email</label>
        <input v-model="form.email" type="email" readonly class="input bg-ink-50 text-ink-500 dark:bg-ink-800/40 dark:text-ink-400" />
      </div>
      <div>
        <label class="label">Password Baru</label>
        <input v-model="form.password" type="password" required autofocus autocomplete="new-password" class="input" />
        <p v-if="form.errors.password" class="error-text">{{ form.errors.password }}</p>
      </div>
      <div>
        <label class="label">Konfirmasi Password Baru</label>
        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="input" />
      </div>
      <button type="submit" :disabled="form.processing" class="btn-accent w-full py-2.5">
        Simpan Password Baru
      </button>
    </form>
  </GuestLayout>
</template>
