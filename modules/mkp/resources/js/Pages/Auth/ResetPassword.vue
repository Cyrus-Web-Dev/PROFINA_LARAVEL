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
    <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Atur Password Baru</h1>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
        <input v-model="form.email" type="email" readonly class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-sm text-slate-500 dark:text-slate-400" />
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Password Baru</label>
        <input v-model="form.password" type="password" required autofocus class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-500">{{ form.errors.password }}</p>
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Konfirmasi Password Baru</label>
        <input v-model="form.password_confirmation" type="password" required class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      </div>
      <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
        Simpan Password Baru
      </button>
    </form>
  </GuestLayout>
</template>
