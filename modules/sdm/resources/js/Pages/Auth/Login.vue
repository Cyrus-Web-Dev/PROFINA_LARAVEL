<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()

defineProps({ status: String })

const form = useForm({ email: '', password: '', remember: false })

function submit() {
  form.post(route('login'), { onFinish: () => form.reset('password') })
}
</script>

<template>
  <GuestLayout>
    <Head title="Login" />
    <h1 class="text-lg font-serif font-semibold text-ink-900 dark:text-ink-100">Masuk ke Akun</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Setelah password benar, kami kirim kode verifikasi ke email kamu.</p>

    <div v-if="status" class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ status }}</div>
    <div v-if="form.errors.email" class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:bg-red-950 dark:text-red-300">{{ form.errors.email }}</div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="label">Email</label>
        <input v-model="form.email" type="email" required autofocus autocomplete="username" class="input" />
      </div>
      <div>
        <label class="label">Password</label>
        <input v-model="form.password" type="password" required autocomplete="current-password" class="input" />
      </div>
      <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
          <input v-model="form.remember" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" />
          Ingat saya
        </label>
        <Link :href="route('password.request')" class="text-sm font-medium text-seal-600 hover:underline dark:text-seal-400">Lupa password?</Link>
      </div>
      <button type="submit" :disabled="form.processing" class="btn-accent w-full py-2.5">
        {{ form.processing ? 'Memproses…' : 'Masuk' }}
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-500 dark:text-ink-400">
      <template v-if="page.props.registration_open">
        Belum punya akun?
        <Link :href="route('register')" class="font-medium text-seal-600 hover:underline dark:text-seal-400">Daftar</Link>
      </template>
      <template v-else>Akun baru dibuat oleh Admin HR.</template>
    </p>
  </GuestLayout>
</template>
