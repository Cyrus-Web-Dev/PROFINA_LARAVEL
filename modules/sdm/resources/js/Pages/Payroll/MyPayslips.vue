<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

defineProps({ items: Array })
function fmtCurrency(n) { return 'Rp ' + Number(n ?? 0).toLocaleString('id-ID') }
</script>

<template>
  <AppLayout>
    <Head title="Slip Gaji Saya" />
    <template #title>Slip Gaji Saya</template>

    <div class="space-y-4">
      <div v-for="item in items" :key="item.id" class="card card-body">
        <div class="flex items-center justify-between">
          <h3 class="font-serif font-semibold text-ink-900 dark:text-ink-100">{{ item.period.label }}</h3>
          <div class="flex items-center gap-2">
            <span class="badge-green">Lunas</span>
            <a :href="route('payroll.items.slip', item.id)" class="btn-secondary btn-sm">Unduh PDF</a>
          </div>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
          <div><p class="text-ink-400">Gaji Pokok</p><p class="font-mono font-medium">{{ fmtCurrency(item.basic_salary) }}</p></div>
          <div><p class="text-ink-400">Potongan</p><p class="font-mono font-medium">{{ fmtCurrency(item.total_deductions) }}</p></div>
          <div><p class="text-ink-400">PPh 21</p><p class="font-mono font-medium">{{ fmtCurrency(item.pph21) }}</p></div>
          <div><p class="text-ink-400">Gaji Bersih</p><p class="font-mono font-semibold text-emerald-700 dark:text-emerald-400">{{ fmtCurrency(item.net_salary) }}</p></div>
        </div>
      </div>
      <p v-if="!items.length" class="text-center text-sm text-ink-400 py-10">Belum ada slip gaji yang terbit.</p>
    </div>
  </AppLayout>
</template>
