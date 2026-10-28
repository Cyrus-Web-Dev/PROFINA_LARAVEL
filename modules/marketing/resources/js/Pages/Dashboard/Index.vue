<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { rp, rp0 } from '@/Composables/format'

const props = defineProps({ stats: Object, series: Array, top: Array, pendingApproval: Array, margin: Object })
const max = computed(() => Math.max(1, ...props.series.map((s) => s.total)))
</script>

<template>
  <AppLayout>
    <Head title="Dashboard" />
    <template #title>Dashboard Marketing & Sales</template>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
      <div class="card card-body"><p class="label">Pelanggan aktif</p><p class="font-mono text-2xl">{{ stats.customers }}</p></div>
      <div class="card card-body"><p class="label">Penawaran terbuka</p><p class="font-mono text-2xl">{{ stats.quotations_open }}</p></div>
      <div class="card card-body"><p class="label">SO berjalan</p><p class="font-mono text-2xl">{{ stats.orders_running }}</p></div>
      <div class="card card-body"><p class="label">Komplain terbuka</p><p class="font-mono text-2xl">{{ stats.complaints_open }}</p></div>
      <div class="card card-body"><p class="label">Omzet bulan ini</p><p class="font-mono text-lg">{{ rp0(stats.omzet_month) }}</p></div>
    </div>

    <div v-if="margin" class="card card-body mt-4 border-seal-300">
      <p class="label">Margin bulan ini (rahasia dapur — hanya terlihat oleh pemegang izin HPP)</p>
      <p class="text-sm">Pendapatan {{ rp0(margin.revenue) }} − HPP {{ rp0(margin.cost) }} = <strong>{{ rp0(margin.profit) }}</strong></p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="font-serif text-base font-semibold">Omzet 6 bulan terakhir</h2></div>
        <div class="card-body">
          <div v-if="series.length" class="flex h-48 items-end gap-3">
            <div v-for="s in series" :key="s.month" class="flex flex-1 flex-col items-center justify-end gap-1" :title="rp(s.total) + ' · ' + s.count + ' SO'">
              <span class="font-mono text-[10px] text-ink-500">{{ rp0(s.total) }}</span>
              <div class="w-full rounded-t bg-seal-500" :style="{ height: Math.max(4, (s.total / max) * 150) + 'px' }"></div>
              <span class="text-xs text-ink-500">{{ s.month }}</span>
            </div>
          </div>
          <p v-else class="py-8 text-center text-sm text-ink-500">Belum ada sales order.</p>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h2 class="font-serif text-base font-semibold">Pelanggan teratas</h2></div>
        <ul class="divide-y divide-ink-100 text-sm dark:divide-ink-800">
          <li v-for="t in top" :key="t.customer" class="flex justify-between px-5 py-2.5"><span>{{ t.customer }}</span><span class="font-mono">{{ rp0(t.total) }}</span></li>
          <li v-if="!top.length" class="px-5 py-6 text-center text-ink-500">Belum ada data.</li>
        </ul>
      </div>
    </div>

    <div v-if="pendingApproval.length" class="card mt-6">
      <div class="card-header"><h2 class="font-serif text-base font-semibold">Penawaran menunggu keputusan</h2></div>
      <ul class="divide-y divide-ink-100 text-sm dark:divide-ink-800">
        <li v-for="p in pendingApproval" :key="p.id" class="flex items-center justify-between px-5 py-2.5">
          <Link :href="route('quotations.show', p.id)" class="font-medium text-seal-700 hover:underline dark:text-seal-400">{{ p.number }}</Link>
          <span>{{ p.customer }}</span><span class="font-mono">{{ rp0(p.total) }}</span>
        </li>
      </ul>
    </div>
  </AppLayout>
</template>
