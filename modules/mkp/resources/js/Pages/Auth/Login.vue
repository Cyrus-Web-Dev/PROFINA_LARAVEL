<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({ status: String, canRegister: { type: Boolean, default: false } })

const form = useForm({ email: '', password: '', remember: false })

function submit() {
  form.post(route('login'), { onFinish: () => form.reset('password') })
}
</script>

<template>
  <GuestLayout>
    <Head title="Login" />
    <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Masuk ke Akun</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Setelah password benar, kami kirim kode verifikasi ke email kamu.</p>

    <div v-if="status" class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ status }}</div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
        <input v-model="form.email" type="email" required autofocus autocomplete="username" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-500">{{ form.errors.email }}</p>
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
        <input v-model="form.password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-500">{{ form.errors.password }}</p>
      </div>
      <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
          <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
          Ingat saya
        </label>
        <Link :href="route('password.request')" class="text-sm font-medium text-emerald-600 hover:underline">Lupa password?</Link>
      </div>
      <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
        Masuk
      </button>
    </form>

    <p v-if="canRegister" class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
      Belum punya akun? <Link :href="route('register')" class="font-medium text-emerald-600 hover:underline">Daftar</Link>
    </p>
  </GuestLayout>
</template>
