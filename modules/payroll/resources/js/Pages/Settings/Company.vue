<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({ values: Object })
const form = useForm({ name: props.values.name ?? '', address: props.values.address ?? '', npwp: props.values.npwp ?? '', work_end: props.values.work_end ?? '17:00' })
</script>

<template>
  <AppLayout>
    <Head title="Profil Perusahaan" />
    <template #title>Profil Perusahaan</template>
    <form class="card card-body max-w-xl space-y-4" @submit.prevent="form.put(route('settings.company.update'))">
      <div><label class="label">Nama perusahaan (tampil di slip gaji)</label><input v-model="form.name" class="input" /><p v-if="form.errors.name" class="mt-1 text-xs text-stamp-600">{{ form.errors.name }}</p></div>
      <div><label class="label">Alamat</label><textarea v-model="form.address" rows="2" class="input" /></div>
      <div><label class="label">NPWP perusahaan (pemotong pajak)</label><input v-model="form.npwp" class="input font-mono" /></div>
      <div><label class="label">Jam pulang kerja normal</label><input v-model="form.work_end" type="time" class="input !w-40" />
        <p class="mt-1 text-xs text-ink-500">Dasar mencocokkan jam lembur hari kerja dengan jam pulang di absensi SDM.</p></div>
      <button class="btn-primary" :disabled="form.processing">Simpan</button>
    </form>
  </AppLayout>
</template>
