<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Money from '@/Components/Money.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'

defineProps({ grades: Array, components: Array })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const g = useForm({ id: null, code: '', name: '', tier: 'staff', min_salary: 0, max_salary: 0, requires_dual_approval: false, is_active: true })
const c = useForm({ id: null, code: '', name: '', type: 'earning', category: 'fixed_allowance', taxable: true, bpjs_base: false, prorated: true, gl_key: 'allowance_expense', is_active: true })
const editG = (x) => Object.assign(g, { ...x })
const editC = (x) => Object.assign(c, { ...x })
const saveG = () => g.post(route('master.grades.save'), { preserveScroll: true, onSuccess: () => g.reset() })
const saveC = () => c.post(route('master.components.save'), { preserveScroll: true, onSuccess: () => c.reset() })
const showG = ref(false)
</script>

<template>
  <AppLayout>
    <Head title="Golongan & Komponen" />
    <template #title>Golongan &amp; Komponen Gaji</template>
    <div class="grid gap-6 xl:grid-cols-2">
      <section class="card">
        <header class="card-header"><div><h2 class="text-base font-semibold">Golongan (grade)</h2><p class="text-xs text-ink-500">Rentang gaji per golongan. Kontrak di luar rentang butuh Direksi.</p></div></header>
        <table class="data-table"><thead><tr><th>Kode</th><th>Nama</th><th class="text-right">Min</th><th class="text-right">Maks</th><th>2 penyetuju</th><th></th></tr></thead>
          <tbody><tr v-for="x in grades" :key="x.id" :class="{ 'opacity-50': !x.is_active }"><td class="font-mono text-xs">{{ x.code }}</td><td>{{ x.name }}</td><td class="text-right"><Money :value="x.min_salary" /></td><td class="text-right"><Money :value="x.max_salary" /></td>
            <td>{{ x.requires_dual_approval ? 'Ya' : '—' }}</td><td><button v-if="can('grades.manage')" class="text-xs text-seal-600 underline" @click="editG(x)">ubah</button></td></tr></tbody></table>
        <form v-if="can('grades.manage')" class="grid grid-cols-2 gap-2 border-t border-ink-100 p-4 dark:border-ink-800" @submit.prevent="saveG">
          <input v-model="g.code" class="input" placeholder="Kode" /><input v-model="g.name" class="input" placeholder="Nama" />
          <select v-model="g.tier" class="input"><option>staff</option><option>supervisor</option><option>manager</option><option>executive</option><option>director</option></select>
          <label class="flex items-center gap-2 text-sm"><input v-model="g.requires_dual_approval" type="checkbox" class="rounded" /> Wajib 2 penyetuju</label>
          <input v-model="g.min_salary" type="number" class="input num" placeholder="Gaji min" /><input v-model="g.max_salary" type="number" class="input num" placeholder="Gaji maks" />
          <button class="btn-primary btn-sm col-span-2">{{ g.id ? 'Simpan perubahan' : 'Tambah golongan' }}</button>
        </form>
      </section>

      <section class="card">
        <header class="card-header"><div><h2 class="text-base font-semibold">Komponen gaji</h2><p class="text-xs text-ink-500">Berlaku untuk kontrak baru. “Kunci GL” diterjemahkan Keuangan menjadi akun buku besar.</p></div></header>
        <table class="data-table"><thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Pajak</th><th>Dasar BPJS</th><th>GL</th><th></th></tr></thead>
          <tbody><tr v-for="x in components" :key="x.id" :class="{ 'opacity-50': !x.is_active }"><td class="font-mono text-xs">{{ x.code }}</td><td>{{ x.name }}</td><td>{{ x.type === 'earning' ? 'Pendapatan' : 'Potongan' }}</td>
            <td>{{ x.taxable ? 'Ya' : '—' }}</td><td>{{ x.bpjs_base ? 'Ya' : '—' }}</td><td class="font-mono text-[10px]">{{ x.gl_key }}</td><td><button v-if="can('components.manage')" class="text-xs text-seal-600 underline" @click="editC(x)">ubah</button></td></tr></tbody></table>
        <form v-if="can('components.manage')" class="grid grid-cols-2 gap-2 border-t border-ink-100 p-4 dark:border-ink-800" @submit.prevent="saveC">
          <input v-model="c.code" class="input" placeholder="KODE_KOMPONEN" /><input v-model="c.name" class="input" placeholder="Nama" />
          <select v-model="c.type" class="input" @change="c.category = c.type === 'earning' ? 'fixed_allowance' : 'deduction'; c.gl_key = c.type === 'earning' ? 'allowance_expense' : 'other_deduction_payable'"><option value="earning">Pendapatan</option><option value="deduction">Potongan</option></select>
          <select v-model="c.gl_key" class="input"><option>allowance_expense</option><option>salary_expense</option><option>bonus_expense</option><option>reimbursement_expense</option><option>other_deduction_payable</option></select>
          <label class="flex items-center gap-2 text-sm"><input v-model="c.taxable" type="checkbox" class="rounded" /> Kena pajak</label><label class="flex items-center gap-2 text-sm"><input v-model="c.bpjs_base" type="checkbox" class="rounded" /> Dasar upah BPJS</label>
          <label class="flex items-center gap-2 text-sm"><input v-model="c.prorated" type="checkbox" class="rounded" /> Prorata</label><label class="flex items-center gap-2 text-sm"><input v-model="c.is_active" type="checkbox" class="rounded" /> Aktif</label>
          <button class="btn-primary btn-sm col-span-2">{{ c.id ? 'Simpan perubahan' : 'Tambah komponen' }}</button>
        </form>
      </section>
    </div>
  </AppLayout>
</template>
