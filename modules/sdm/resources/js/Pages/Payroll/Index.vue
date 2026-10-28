<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({ periods: Array, external: Boolean })

const showForm = ref(false)
const form = useForm({ period_month: new Date().getMonth() + 1, period_year: new Date().getFullYear() })
function submit() {
  form.post(route('payroll.store'), { onSuccess: () => { showForm.value = false } })
}

const statusBadge = { draft: 'badge-amber', processed: 'badge-seal', paid: 'badge-green' }
const statusLabels = { draft: 'Draf', processed: 'Diproses', paid: 'Lunas' }
</script>

<template>
  <AppLayout>
    <Head title="Payroll" />
    <template #title>Payroll</template>
    <div v-if="external" class="mb-4 rounded-md border border-seal-300 bg-seal-50 px-4 py-3 text-sm text-seal-900">
      Modul payroll SDM sudah <b>dialihkan ke PAYBRIDGE</b>. Data periode lama tetap bisa dilihat, tetapi menghitung, mengubah, dan menandai lunas dinonaktifkan.
    </div>
    <template #actions>
      <button @click="showForm = !showForm" class="btn-accent btn-sm">{{ showForm ? 'Batal' : '+ Periode Baru' }}</button>
    </template>

    <form v-if="showForm" @submit.prevent="submit" class="card card-body mb-6 flex flex-wrap items-end gap-4">
      <div>
        <label class="label">Bulan</label>
        <select v-model.number="form.period_month" class="input">
          <option v-for="m in 12" :key="m" :value="m">{{ m }}</option>
        </select>
      </div>
      <div>
        <label class="label">Tahun</label>
        <input v-model.number="form.period_year" type="number" class="input w-28" />
      </div>
      <button type="submit" class="btn-accent">Buat Periode</button>
    </form>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Periode</th><th>Jml. Karyawan</th><th>Status</th><th>Diproses</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="p in periods" :key="p.id">
              <td class="font-medium">{{ p.label }}</td>
              <td class="num">{{ p.items_count }}</td>
              <td><span :class="statusBadge[p.status]">{{ statusLabels[p.status] }}</span></td>
              <td class="text-ink-400 text-xs">{{ p.processed_at || '-' }}</td>
              <td class="text-right"><Link :href="route('payroll.show', p.id)" class="btn-ghost btn-sm">Buka</Link></td>
            </tr>
            <tr v-if="!periods.length"><td colspan="5" class="py-10 text-center text-sm text-ink-400">Belum ada periode payroll.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
