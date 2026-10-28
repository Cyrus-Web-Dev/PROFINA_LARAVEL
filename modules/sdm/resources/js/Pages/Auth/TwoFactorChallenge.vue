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
  form.post(route('two-factor.verify'), { onError: () => { form.reset('code') } })
}
function resend() {
  router.post(route('two-factor.resend'), {}, {
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
    <Head title="Verifikasi Login" />
    <h1 class="text-lg font-serif font-semibold text-ink-900 dark:text-ink-100">Verifikasi Login</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Kami kirim kode {{ codeLength }} digit ke <strong>{{ maskedEmail }}</strong>. Cek inbox atau folder spam.</p>

    <div v-if="status" class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ status }}</div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="label">Kode Verifikasi</label>
        <input v-model="form.code" type="text" inputmode="numeric" :maxlength="codeLength" autocomplete="one-time-code" autofocus
          class="input mt-1 text-center text-2xl font-mono font-bold tracking-[0.5em]" :placeholder="'-'.repeat(codeLength)" />
        <p v-if="form.errors.code" class="error-text">{{ form.errors.code }}</p>
      </div>
      <button type="submit" :disabled="form.processing" class="btn-accent w-full py-2.5">
        Verifikasi &amp; Masuk
      </button>
    </form>

    <button @click="resend" :disabled="cooldown > 0" class="mt-4 w-full text-center text-sm font-medium text-seal-600 hover:underline disabled:text-ink-300 dark:text-seal-400 dark:disabled:text-ink-600">
      {{ cooldown > 0 ? `Kirim ulang kode (${cooldown}s)` : 'Kirim ulang kode' }}
    </button>
  </GuestLayout>
</template>
