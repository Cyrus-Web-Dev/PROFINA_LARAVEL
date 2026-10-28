<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

defineProps({ linked: Boolean, portalUrl: String, payslips: Array })
const rp = (v) => 'Rp ' + Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 })
</script>

<template>
  <AppLayout>
    <Head title="Slip Gaji (Payroll)" />
    <template #title>Slip Gaji dari Payroll</template>
    <p v-if="!linked" class="card card-body max-w-xl text-sm">Akunmu belum dihubungkan ke data karyawan.</p>
    <template v-else>
      <p class="mb-4 max-w-2xl text-sm text-ink-600">Ringkasan slip gaji yang diterbitkan PAYBRIDGE. Rincian lengkap &amp; PDF diunduh di portal Payroll.
        <a v-if="portalUrl" :href="portalUrl" target="_blank" class="text-seal-700 underline">Buka PAYBRIDGE →</a></p>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article v-for="p in payslips" :key="p.id" class="card card-body"><p class="text-xs uppercase tracking-wider text-ink-400">{{ p.period }} · {{ p.type }}</p>
          <p class="mt-2 text-2xl font-semibold">{{ rp(p.net) }}</p><p class="text-xs text-ink-500">gaji bersih · bruto {{ rp(p.gross) }}</p><p class="mt-2 font-mono text-[10px] text-ink-400">{{ p.run }}</p></article>
        <p v-if="!payslips.length" class="text-sm text-ink-400">Belum ada slip yang terbit.</p>
      </div>
    </template>
  </AppLayout>
</template>
