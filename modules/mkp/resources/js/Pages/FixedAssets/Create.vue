<script setup>
import { todayLocal } from '@/utils'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ assetAccounts: Array, accumulatedDepreciationAccounts: Array, depreciationExpenseAccounts: Array, paymentAccounts: Array, nextNumber: String })

const form = useForm({
  name: '', category: '', acquisition_date: todayLocal(),
  acquisition_cost: 0, salvage_value: 0, useful_life_months: 36,
  asset_account_id: props.assetAccounts[0]?.id ?? '',
  accumulated_depreciation_account_id: props.accumulatedDepreciationAccounts[0]?.id ?? '',
  depreciation_expense_account_id: props.depreciationExpenseAccounts[0]?.id ?? '',
  payment_account_id: props.paymentAccounts[0]?.id ?? '',
})

const monthlyDepreciation = computed(() => {
  const life = Number(form.useful_life_months) || 1
  return ((Number(form.acquisition_cost) || 0) - (Number(form.salvage_value) || 0)) / life
})
function formatIDR(v) {
  return new Intl.NumberFormat('id-ID').format(v || 0)
}
function submit() {
  form.post(route('fixed-assets.store'))
}
</script>

<template>
  <AppLayout>
    <template #title>Catat Aset Tetap Baru</template>

    <form @submit.prevent="submit" class="mx-auto max-w-2xl space-y-6">
      <ErrorSummary :errors="form.errors" />
      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">No. Aset</label><input :value="nextNumber" disabled class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-sm text-slate-500 dark:text-slate-400" /></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Kategori</label><input v-model="form.category" type="text" placeholder="Kendaraan" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        </div>
        <div class="mt-4"><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nama Aset</label><input v-model="form.name" type="text" placeholder="Mobil Operasional Toyota Avanza" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>

        <div class="mt-4 grid grid-cols-3 gap-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Tgl Perolehan</label><input v-model="form.acquisition_date" type="date" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Harga Perolehan</label><input v-model.number="form.acquisition_cost" type="number" min="0" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nilai Residu</label><input v-model.number="form.salvage_value" type="number" min="0" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        </div>
        <div class="mt-4"><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Umur Ekonomis (bulan)</label><input v-model.number="form.useful_life_months" type="number" min="1" class="mt-1 w-40 rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>

        <div class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Estimasi depresiasi per bulan (garis lurus): <strong>{{ formatIDR(monthlyDepreciation) }}</strong>
        </div>
      </div>

      <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Pemetaan Akun</h3>
        <div class="mt-4 space-y-4">
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Akun Aset Tetap</label>
            <select v-model="form.asset_account_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option v-for="a in assetAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Akun Akumulasi Depresiasi</label>
            <select v-model="form.accumulated_depreciation_account_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option v-for="a in accumulatedDepreciationAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Akun Beban Depresiasi</label>
            <select v-model="form.depreciation_expense_account_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option v-for="a in depreciationExpenseAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Dibayar dari Akun</label>
            <select v-model="form.payment_account_id" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option v-for="a in paymentAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40">
          Simpan & Posting Jurnal Perolehan
        </button>
      </div>
    </form>
  </AppLayout>
</template>
