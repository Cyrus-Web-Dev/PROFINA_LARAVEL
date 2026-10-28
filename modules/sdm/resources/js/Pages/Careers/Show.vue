<script setup>
import { ref } from 'vue'
import TurnstileWidget from '@/Components/TurnstileWidget.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const captcha = ref(null)

const props = defineProps({ vacancy: Object })
const typeLabels = { tetap: 'Tetap (PKWTT)', kontrak: 'Kontrak (PKWT)', magang: 'Magang', harian: 'Harian Lepas' }

const submitted = ref(false)
const form = useForm({ full_name: '', email: '', phone: '', cover_letter: '', resume: null, turnstile_token: '' })
function submit() {
  form.post(route('careers.apply', props.vacancy.slug), {
    forceFormData: true,
    onSuccess: () => { submitted.value = true; form.reset() },
    onFinish: () => { captcha.value?.reset(); form.turnstile_token = '' },
  })
}
</script>

<template>
  <Head :title="vacancy.title" />
  <div class="min-h-screen bg-paper dark:bg-ink-950">
    <header class="border-b border-ink-200 dark:border-ink-800">
      <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
        <Link :href="route('welcome')" class="flex items-center gap-2.5">
          <div class="flex h-9 w-9 items-center justify-center rounded-md bg-seal-500 text-ink-950 font-serif font-bold">S</div>
          <span class="font-serif font-semibold text-ink-900 dark:text-ink-100">SDM HRIS</span>
        </Link>
        <Link :href="route('careers.index')" class="text-sm text-ink-500 hover:underline">&larr; Semua Lowongan</Link>
      </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-12">
      <h1 class="font-serif text-3xl font-bold text-ink-900 dark:text-ink-50">{{ vacancy.title }}</h1>
      <p class="mt-2 text-ink-500">{{ vacancy.department?.name || 'Umum' }} &middot; {{ typeLabels[vacancy.employment_type] }} &middot; {{ vacancy.slots }} posisi</p>

      <div class="card card-body mt-6 space-y-4">
        <div><h2 class="font-serif font-semibold mb-1">Deskripsi Pekerjaan</h2><p class="whitespace-pre-line text-sm text-ink-700 dark:text-ink-300">{{ vacancy.description }}</p></div>
        <div><h2 class="font-serif font-semibold mb-1">Kualifikasi</h2><p class="whitespace-pre-line text-sm text-ink-700 dark:text-ink-300">{{ vacancy.requirements }}</p></div>
      </div>

      <div class="card card-body mt-6">
        <h2 class="font-serif font-semibold mb-4">Lamar Posisi Ini</h2>

        <div v-if="submitted" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
          Lamaran kamu berhasil dikirim. Tim HR akan menghubungimu bila lolos seleksi administrasi.
        </div>

        <form v-else @submit.prevent="submit" class="space-y-4">
          <div><label class="label">Nama Lengkap</label><input v-model="form.full_name" class="input" required /></div>
          <div><label class="label">Email</label><input v-model="form.email" type="email" class="input" required /></div>
          <div><label class="label">No. HP</label><input v-model="form.phone" class="input" /></div>
          <div><label class="label">Surat Lamaran (opsional)</label><textarea v-model="form.cover_letter" rows="4" class="input"></textarea></div>
          <div>
            <label class="label">CV (PDF, maks. 5MB)</label>
            <input type="file" accept=".pdf" class="input" @change="form.resume = $event.target.files[0]" required />
            <p v-if="form.errors.resume" class="error-text">{{ form.errors.resume }}</p>
          </div>
          <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
          <TurnstileWidget v-if="page.props.turnstile_site_key" ref="captcha" :site-key="page.props.turnstile_site_key"
            @verified="(t) => form.turnstile_token = t" @expired="form.turnstile_token = ''" />
          <button type="submit" :disabled="form.processing" class="btn-accent w-full">{{ form.processing ? 'Mengirim…' : 'Kirim Lamaran' }}</button>
        </form>
      </div>
    </main>
  </div>
</template>
