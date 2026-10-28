<script setup>
import { useCan } from '@/permissions'
import { todayLocal } from '@/utils'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ asset: Object })
const showModal = ref(false)
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

const form = useForm({ period_date: todayLocal() })

function submit() {
  form.post(route('fixed-assets.run-depreciation', props.asset.id), { onSuccess: () => { showModal.value = false } })
}
</script>

<template>
  <AppLayout>
    <template #title>{{ asset.name }}</template>
    <template #actions>
      <button v-if="can('fixed-asset.manage') && (asset.status === 'active')" @click="showModal = true" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
        Jalankan Depresiasi Bulan Ini
      </button>
    </template>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-4">
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700"><p class="text-xs text-slate-400 dark:text-slate-500">Harga Perolehan</p><p class="mt-1 text-xl font-semibold text-slate-800 dark:text-slate-100">{{ formatIDR(asset.acquisition_cost) }}</p></div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700"><p class="text-xs text-slate-400 dark:text-slate-500">Akumulasi Depresiasi</p><p class="mt-1 text-xl font-semibold text-amber-600">{{ formatIDR(asset.accumulated_depreciation) }}</p></div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700"><p class="text-xs text-slate-400 dark:text-slate-500">Nilai Buku</p><p class="mt-1 text-xl font-semibold text-emerald-600">{{ formatIDR(asset.book_value) }}</p></div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-5 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700"><p class="text-xs text-slate-400 dark:text-slate-500">Progres Depresiasi</p><p class="mt-1 text-xl font-semibold text-slate-800 dark:text-slate-100">{{ asset.months_depreciated }} / {{ asset.useful_life_months }} bln</p></div>
    </div>

    <div class="mt-6 rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Riwayat Depresiasi</h3>
      <div class="overflow-x-auto">
      <table class="mt-4 w-full text-sm">
        <thead><tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
          <th class="py-2">Periode</th><th class="py-2 text-right">Jumlah</th><th class="py-2">Jurnal</th>
        </tr></thead>
        <tbody>
          <tr v-for="d in asset.depreciations" :key="d.id" class="border-b border-slate-50 dark:border-slate-800">
            <td class="py-2">{{ d.period_date }}</td>
            <td class="py-2 text-right font-mono">{{ formatIDR(d.amount) }}</td>
            <td class="py-2"><Link v-if="d.journal" :href="route('journals.show', d.journal.id)" class="text-xs font-medium text-emerald-600 hover:underline">{{ d.journal.journal_number }}</Link></td>
          </tr>
          <tr v-if="asset.depreciations.length === 0"><td colspan="3" class="py-8 text-center text-slate-400 dark:text-slate-500">Belum ada depresiasi yang dijalankan.</td></tr>
        </tbody>
      </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Jalankan Depresiasi</h3>
        <form @submit.prevent="submit" class="mt-4 space-y-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Tanggal Periode</label><input v-model="form.period_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <p class="text-xs text-slate-400 dark:text-slate-500">Jumlah depresiasi: {{ formatIDR(asset.monthly_depreciation) }}</p>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Posting</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
