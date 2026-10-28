<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({ period: Object, items: Array })

function fmtCurrency(n) { return 'Rp ' + Number(n ?? 0).toLocaleString('id-ID') }

function process() {
  if (confirm('Hitung payroll untuk semua karyawan aktif? Ini akan mengisi/menimpa item periode ini.')) {
    router.post(route('payroll.process', props.period.id))
  }
}
function markPaid() {
  if (confirm('Tandai periode ini LUNAS? Slip gaji akan dikirim ke email masing-masing karyawan dan data tidak bisa diubah lagi.')) {
    router.post(route('payroll.mark-paid', props.period.id))
  }
}

const editing = ref(null)
const form = useForm({ allowances: [], other_deductions: [], overtime_amount: 0, notes: '' })
function openEdit(item) {
  editing.value = item
  form.allowances = item.allowances ? [...item.allowances] : []
  form.other_deductions = item.other_deductions ? [...item.other_deductions] : []
  form.overtime_amount = item.overtime_amount ?? 0
  form.notes = item.notes ?? ''
}
function addRow(list) { list.push({ label: '', amount: 0 }) }
function save() {
  form.put(route('payroll.items.update', editing.value.id), { onSuccess: () => { editing.value = null } })
}
</script>

<template>
  <AppLayout>
    <Head :title="period.label" />
    <template #title>Payroll — {{ period.label }}</template>
    <template #actions>
      <button v-if="period.status === 'draft'" @click="process" class="btn-accent btn-sm">Hitung Payroll</button>
      <button v-if="period.status === 'processed'" @click="markPaid" class="btn-accent btn-sm">Tandai Lunas</button>
    </template>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Karyawan</th><th>Gaji Pokok</th><th>Tunjangan</th><th>Potongan</th><th>PPh21</th><th>Gaji Bersih</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="font-medium">{{ item.employee.full_name }}</td>
              <td class="num">{{ fmtCurrency(item.basic_salary) }}</td>
              <td class="num">{{ fmtCurrency((item.allowances || []).reduce((s,a) => s + Number(a.amount), 0) + Number(item.overtime_amount)) }}</td>
              <td class="num">{{ fmtCurrency(item.total_deductions) }}</td>
              <td class="num">{{ fmtCurrency(item.pph21) }}</td>
              <td class="num font-semibold">{{ fmtCurrency(item.net_salary) }}</td>
              <td v-if="period.status !== 'paid'" class="text-right"><button @click="openEdit(item)" class="btn-ghost btn-sm">Koreksi</button></td>
              <td v-else class="text-right"><a :href="route('payroll.items.slip', item.id)" class="btn-ghost btn-sm">Unduh PDF</a></td>
            </tr>
            <tr v-if="!items.length"><td colspan="7" class="py-10 text-center text-sm text-ink-400">Belum ada item. Klik "Hitung Payroll".</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto">
      <form @submit.prevent="save" class="card w-full max-w-lg my-8">
        <div class="card-header"><h3 class="font-serif font-semibold">Koreksi: {{ editing.employee.full_name }}</h3></div>
        <div class="card-body space-y-4">
          <div>
            <div class="flex items-center justify-between"><label class="label mb-0">Tunjangan</label><button type="button" @click="addRow(form.allowances)" class="text-xs text-seal-600">+ tambah</button></div>
            <div v-for="(a, i) in form.allowances" :key="i" class="mt-2 flex gap-2">
              <input v-model="a.label" placeholder="Label" class="input" />
              <input v-model.number="a.amount" type="number" placeholder="Nominal" class="input font-mono w-32" />
            </div>
          </div>
          <div>
            <div class="flex items-center justify-between"><label class="label mb-0">Potongan Lain</label><button type="button" @click="addRow(form.other_deductions)" class="text-xs text-seal-600">+ tambah</button></div>
            <div v-for="(a, i) in form.other_deductions" :key="i" class="mt-2 flex gap-2">
              <input v-model="a.label" placeholder="Label" class="input" />
              <input v-model.number="a.amount" type="number" placeholder="Nominal" class="input font-mono w-32" />
            </div>
          </div>
          <div><label class="label">Lembur (Rp)</label><input v-model.number="form.overtime_amount" type="number" class="input font-mono" /></div>
          <div><label class="label">Catatan</label><textarea v-model="form.notes" rows="2" class="input"></textarea></div>
        </div>
        <div class="card-body pt-0 flex gap-2">
          <button type="submit" class="btn-accent btn-sm">Hitung Ulang &amp; Simpan</button>
          <button type="button" @click="editing = null" class="btn-secondary btn-sm">Batal</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
