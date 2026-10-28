<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({ status: String })
const form = useForm({ email: '' })

function submit() {
  form.post(route('password.email'))
}
</script>

<template>
  <GuestLayout>
    <Head title="Lupa Password" />
    <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Lupa Password</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Masukkan email terdaftar, kami kirim link untuk atur ulang password.</p>

    <div v-if="status" class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ status }}</div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
        <input v-model="form.email" type="email" required autofocus class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-500">{{ form.errors.email }}</p>
      </div>
      <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
        Kirim Link Reset Password
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
      <Link :href="route('login')" class="font-medium text-emerald-600 hover:underline">← Kembali ke Login</Link>
    </p>
  </GuestLayout>
</template>
