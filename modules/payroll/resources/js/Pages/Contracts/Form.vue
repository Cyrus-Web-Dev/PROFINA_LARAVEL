<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Money from '@/Components/Money.vue'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({ employees: Array, grades: Array, components: Array, employeeId: Number })
const form = useForm({
  employee_id: props.employeeId ?? '', grade_id: '', contract_type: 'pkwt', base_salary: '', effective_date: new Date().toISOString().slice(0, 10), end_date: '',
  jkk_class: 1, bpjs_kes_enrolled: true, bpjs_tk_enrolled: true, tax_scheme: 'ter', thr_eligible: true, overtime_eligible: false, document_ref: '', reason: '', components: [],
})
const grade = computed(() => props.grades.find((g) => g.id === Number(form.grade_id)))
const band = computed(() => (grade.value && form.base_salary ? Number(form.base_salary) >= grade.value.min_salary && Number(form.base_salary) <= grade.value.max_salary : null))
const addComp = () => form.components.push({ pay_component_id: props.components[0]?.id ?? '', amount: '' })
const err = (k) => form.errors[k]
</script>

<template>
  <AppLayout>
    <Head title="Usulkan Kontrak" />
    <template #title>Usulkan Kontrak Kompensasi</template>
    <form class="grid gap-6 lg:grid-cols-3" @submit.prevent="form.post(route('contracts.store'))">
      <div class="space-y-6 lg:col-span-2">
        <section class="card card-body grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2"><label class="label">Karyawan</label>
            <select v-model="form.employee_id" class="input"><option value="">— pilih —</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.nip }} · {{ e.full_name }}</option></select><p v-if="err('employee_id')" class="mt-1 text-xs text-stamp-600">{{ err('employee_id') }}</p></div>
          <div><label class="label">Golongan</label><select v-model="form.grade_id" class="input"><option value="">— tanpa golongan —</option><option v-for="g in grades" :key="g.id" :value="g.id">{{ g.code }} · {{ g.name }}</option></select></div>
          <div><label class="label">Jenis kontrak</label><select v-model="form.contract_type" class="input"><option value="pkwt">PKWT (kontrak)</option><option value="pkwtt">PKWTT (tetap)</option><option value="magang">Magang</option><option value="harian">Harian</option><option value="direksi">Direksi</option></select></div>
          <div><label class="label">Gaji pokok / bulan (Rp)</label><input v-model="form.base_salary" type="number" min="0" class="input num" /><p v-if="err('base_salary')" class="mt-1 text-xs text-stamp-600">{{ err('base_salary') }}</p>
            <p v-if="grade" class="mt-1 text-xs" :class="band === false ? 'font-semibold text-stamp-600' : 'text-ink-500'">Rentang golongan: <Money :value="grade.min_salary" /> – <Money :value="grade.max_salary" /><span v-if="band === false"> · DI LUAR RENTANG → wajib persetujuan Direksi</span></p></div>
          <div><label class="label">Dokumen kontrak (nomor/ID)</label><input v-model="form.document_ref" class="input" placeholder="mis. PKWT/2026/0123" /></div>
          <div><label class="label">Berlaku mulai</label><input v-model="form.effective_date" type="date" class="input" /><p v-if="err('effective_date')" class="mt-1 text-xs text-stamp-600">{{ err('effective_date') }}</p></div>
          <div><label class="label">Berakhir (kosongkan bila tetap)</label><input v-model="form.end_date" type="date" class="input" /></div>
          <div class="sm:col-span-2"><label class="label">Alasan / dasar perubahan (wajib, min. 10 karakter)</label><textarea v-model="form.reason" rows="2" class="input" /><p v-if="err('reason')" class="mt-1 text-xs text-stamp-600">{{ err('reason') }}</p></div>
        </section>

        <section class="card">
          <header class="card-header"><h2 class="text-base font-semibold">Tunjangan & potongan tetap</h2><button type="button" class="btn-secondary btn-sm" @click="addComp">+ Tambah</button></header>
          <div class="card-body space-y-2">
            <div v-for="(c, i) in form.components" :key="i" class="flex gap-2">
              <select v-model="c.pay_component_id" class="input"><option v-for="x in components" :key="x.id" :value="x.id">{{ x.name }} ({{ x.type === 'earning' ? '+' : '−' }})</option></select>
              <input v-model="c.amount" type="number" min="0" class="input num !w-44" placeholder="Rp / bulan" />
              <button type="button" class="btn-ghost btn-sm" @click="form.components.splice(i, 1)">✕</button>
            </div>
            <p v-if="!form.components.length" class="text-sm text-ink-400">Belum ada. Gaji pokok saja.</p>
          </div>
        </section>
      </div>

      <aside class="space-y-6">
        <section class="card card-body space-y-3 text-sm">
          <h2 class="font-semibold">Pengaturan BPJS, pajak & hak</h2>
          <label class="flex items-center gap-2"><input v-model="form.bpjs_kes_enrolled" type="checkbox" class="rounded" /> Peserta BPJS Kesehatan</label>
          <label class="flex items-center gap-2"><input v-model="form.bpjs_tk_enrolled" type="checkbox" class="rounded" /> Peserta BPJS Ketenagakerjaan</label>
          <div><label class="label">Kelas risiko JKK</label><select v-model="form.jkk_class" class="input"><option v-for="n in 5" :key="n" :value="n">Kelas {{ n }}</option></select></div>
          <div><label class="label">Skema pajak</label><select v-model="form.tax_scheme" class="input"><option value="ter">PPh 21 (TER / Pasal 17)</option><option value="exempt">Tidak dipotong (perlu dasar konsultan pajak)</option></select></div>
          <label class="flex items-center gap-2"><input v-model="form.thr_eligible" type="checkbox" class="rounded" /> Berhak THR</label>
          <label class="flex items-center gap-2"><input v-model="form.overtime_eligible" type="checkbox" class="rounded" /> Berhak upah lembur</label>
        </section>
        <section class="card card-body text-xs text-ink-500">
          Setelah disimpan, kontrak masuk antrean: <b>verifikasi Payroll → persetujuan Keuangan</b>{{ band === false || grade?.requires_dual_approval ? ' → persetujuan Direksi' : '' }}. Pengusul tidak boleh menyetujui kontrak sendiri.
        </section>
        <button class="btn-primary w-full" :disabled="form.processing">Ajukan kontrak</button>
      </aside>
    </form>
  </AppLayout>
</template>
