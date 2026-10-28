<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { ref } from 'vue'
import TurnstileWidget from '@/Components/TurnstileWidget.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const captcha = ref(null)

defineProps({ status: String })
const form = useForm({ email: '', turnstile_token: '' })

function submit() {
  form.post(route('password.email'), { onFinish: () => { captcha.value?.reset(); form.turnstile_token = '' } })
}
</script>

<template>
  <GuestLayout>
    <Head title="Lupa Password" />
    <h1 class="text-lg font-serif font-semibold text-ink-900 dark:text-ink-100">Lupa Password</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Masukkan email terdaftar, kami kirim link untuk atur ulang password.</p>

    <div v-if="status" class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ status }}</div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="label">Email</label>
        <input v-model="form.email" type="email" required autofocus class="input" />
        <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
      </div>
      <TurnstileWidget v-if="page.props.turnstile_site_key" ref="captcha" :site-key="page.props.turnstile_site_key"
        @verified="(t) => form.turnstile_token = t" @expired="form.turnstile_token = ''" />
      <button type="submit" :disabled="form.processing" class="btn-accent w-full py-2.5">
        Kirim Link Reset Password
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-500 dark:text-ink-400">
      <Link :href="route('login')" class="font-medium text-seal-600 hover:underline dark:text-seal-400">&larr; Kembali ke Login</Link>
    </p>
  </GuestLayout>
</template>
