<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({ isFirstUser: { type: Boolean, default: false } })

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
  form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <GuestLayout>
    <Head title="Daftar" />
    <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Buat Akun Baru</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kami akan mengirim kode verifikasi ke email untuk mengaktifkan akunmu.</p>

    <div v-if="isFirstUser" class="mt-4 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-700 dark:bg-sky-950 dark:text-sky-300">
      Belum ada pengguna. Akun pertama yang dibuat otomatis menjadi <strong>admin</strong>.
    </div>

    <div v-else class="mt-4 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-700 dark:bg-sky-950 dark:text-sky-300">
      Setelah emailmu terverifikasi, pendaftaran perlu <strong>disetujui Manager</strong> sebelum kamu bisa masuk. Kamu akan menerima email hasilnya.
    </div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nama Lengkap</label>
        <input v-model="form.name" type="text" required autofocus class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-500">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
        <input v-model="form.email" type="email" required class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-500">{{ form.errors.email }}</p>
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Password</label>
        <input v-model="form.password" type="password" required class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-500">{{ form.errors.password }}</p>
      </div>
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Konfirmasi Password</label>
        <input v-model="form.password_confirmation" type="password" required class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
      </div>
      <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
        Daftar & Kirim Kode Verifikasi
      </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
      Sudah punya akun? <Link :href="route('login')" class="font-medium text-emerald-600 hover:underline">Masuk</Link>
    </p>
  </GuestLayout>
</template>
