<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
  maskedEmail: String,
  status: String,
  resendIn: { type: Number, default: 0 },
  codeLength: { type: Number, default: 6 },
})
const cooldown = ref(props.resendIn)
let timer = null

const form = useForm({ code: '' })

function submit() {
  form.post(route('register.verify'), {
    onError: () => { form.reset('code') },
  })
}
function resend() {
  router.post(route('register.verify.resend'), {}, {
    onSuccess: () => { cooldown.value = 60; startTimer() },
  })
}
function startTimer() {
  clearInterval(timer)
  timer = setInterval(() => { if (cooldown.value > 0) cooldown.value--; else clearInterval(timer) }, 1000)
}
onMounted(startTimer)
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <GuestLayout>
    <Head title="Verifikasi Email" />
    <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Verifikasi Email</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kami kirim kode {{ codeLength }} digit ke <strong>{{ maskedEmail }}</strong>. Cek inbox atau folder spam.</p>

    <div v-if="status" class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ status }}</div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Kode Verifikasi</label>
        <input v-model="form.code" type="text" inputmode="numeric" :maxlength="codeLength" autocomplete="one-time-code" autofocus
          class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-center text-2xl font-bold tracking-[0.5em] text-slate-800 dark:text-slate-100" :placeholder="'-'.repeat(codeLength)" />
        <p v-if="form.errors.code" class="mt-1 text-xs text-rose-500">{{ form.errors.code }}</p>
      </div>
      <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
        Verifikasi & Aktifkan Akun
      </button>
    </form>

    <button @click="resend" :disabled="cooldown > 0" class="mt-4 w-full text-center text-sm font-medium text-emerald-600 hover:underline disabled:text-slate-300">
      {{ cooldown > 0 ? `Kirim ulang kode (${cooldown}s)` : 'Kirim ulang kode' }}
    </button>
  </GuestLayout>
</template>
