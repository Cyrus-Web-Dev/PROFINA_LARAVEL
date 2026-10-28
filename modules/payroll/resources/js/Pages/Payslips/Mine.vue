<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Money from '@/Components/Money.vue'
import { Head } from '@inertiajs/vue3'

defineProps({ linked: Boolean, payslips: Array })
</script>

<template>
  <AppLayout>
    <Head title="Slip Gaji Saya" />
    <template #title>Slip Gaji Saya</template>
    <p v-if="!linked" class="card card-body max-w-xl text-sm">Akunmu belum dihubungkan ke data karyawan, jadi slip gaji belum bisa ditampilkan. Minta Admin Payroll menghubungkannya di Kelola Pengguna.</p>
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article v-for="p in payslips" :key="p.id" class="card card-body">
        <p class="text-xs uppercase tracking-wider text-ink-400">{{ p.period }}</p><p class="font-medium">{{ p.title }}</p>
        <p class="mt-3 text-2xl font-semibold text-seal-700"><Money :value="p.net" /></p><p class="text-xs text-ink-500">gaji bersih · bruto <Money :value="p.gross" /> · PPh 21 <Money :value="p.pph21" /> · BPJS <Money :value="p.bpjs" /></p>
        <a :href="route('payslips.pdf', p.id)" target="_blank" class="btn-secondary btn-sm mt-4 self-start">Unduh slip (PDF)</a>
      </article>
      <p v-if="!payslips.length" class="text-sm text-ink-400">Belum ada slip yang terbit.</p>
    </div>
  </AppLayout>
</template>
