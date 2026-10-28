<script setup>
import { ref } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import TurnstileWidget from '@/Components/TurnstileWidget.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

defineProps({ isFirstUser: Boolean })
const page = usePage()
const captcha = ref(null)

const form = useForm({ name: '', email: '', registration_nip: '', password: '', password_confirmation: '', turnstile_token: '' })

function submit() {
  form.post(route('register'), {
    onFinish: () => { form.reset('password', 'password_confirmation'); captcha.value?.reset(); form.turnstile_token = '' },
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="Daftar" />
    <h1 class="text-lg font-serif font-semibold text-ink-900 dark:text-ink-100">Buat Akun</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
      <template v-if="isFirstUser">Kamu pendaftar pertama — akunmu otomatis menjadi <strong>Super Admin</strong>.</template>
      <template v-else>Setelah email diverifikasi, akun menunggu persetujuan Admin Payroll sebelum bisa dipakai.</template>
    </p>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="label">Nama Lengkap</label>
        <input v-model="form.name" required autofocus autocomplete="name" class="input" />
        <p v-if="form.errors.name" class="error-text">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="label">Email</label>
        <input v-model="form.email" type="email" required autocomplete="username" class="input" />
        <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
      </div>
      <div v-if="!isFirstUser">
        <label class="label">NIP (bila sudah punya)</label>
        <input v-model="form.registration_nip" class="input font-mono" placeholder="mis. 2024-0012" />
        <p v-if="form.errors.registration_nip" class="error-text">{{ form.errors.registration_nip }}</p>
        <p class="mt-1 text-xs text-ink-400">Membantu Admin Payroll mencocokkan akunmu dengan data kepegawaian.</p>
      </div>
      <div>
        <label class="label">Password</label>
        <input v-model="form.password" type="password" required autocomplete="new-password" class="input" />
        <p v-if="form.errors.password" class="error-text">{{ form.errors.password }}</p>
        <p class="mt-1 text-xs text-ink-400">Minimal 8 karakter, campuran huruf besar &amp; kecil, ada angka, dan bukan password yang pernah bocor.</p>
      </div>
      <div>
        <label class="label">Ulangi Password</label>
        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="input" />
      </div>

      <TurnstileWidget v-if="page.props.turnstile_site_key" ref="captcha" :site-key="page.props.turnstile_site_key"
        @verified="(t) => form.turnstile_token = t" @expired="form.turnstile_token = ''" />

      <button type="submit" :disabled="form.processing" class="btn-accent w-full py-2.5">
        {{ form.processing ? 'Memproses…' : 'Daftar & Kirim Kode Verifikasi' }}
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-500 dark:text-ink-400">
      Sudah punya akun?
      <Link :href="route('login')" class="font-medium text-seal-600 hover:underline dark:text-seal-400">Masuk</Link>
    </p>
  </GuestLayout>
</template>
