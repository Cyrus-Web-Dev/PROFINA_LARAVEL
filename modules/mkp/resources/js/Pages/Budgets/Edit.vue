<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({ year: Number, month: Number, accounts: Array })
const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']

const form = useForm({
  year: props.year,
  month: props.month,
  items: props.accounts.map(a => ({ account_id: a.id, amount: a.budget_amount })),
})

function submit() {
  form.put(route('budgets.update'))
}
</script>

<template>
  <AppLayout>
    <template #title>Edit Anggaran - {{ monthNames[month - 1] }} {{ year }}</template>

    <form @submit.prevent="submit">
      <ErrorSummary :errors="form.errors" />
      <div class="overflow-x-auto rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
              <th class="px-4 py-3">Akun</th><th class="px-4 py-3">Tipe</th><th class="px-4 py-3 w-56 text-right">Jumlah Anggaran</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in form.items" :key="item.account_id" class="border-b border-slate-50 dark:border-slate-800">
              <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ accounts[i].code }} - {{ accounts[i].name }}</td>
              <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ accounts[i].type }}</td>
              <td class="px-4 py-3"><input v-model.number="item.amount" type="number" min="0" step="1000" class="w-full rounded-lg border-slate-200 dark:border-slate-700 text-right text-sm" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-end">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 disabled:opacity-40">
          Simpan Anggaran
        </button>
      </div>
    </form>
  </AppLayout>
</template>
