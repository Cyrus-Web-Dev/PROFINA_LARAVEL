<script setup>
import { reactive, ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ inputs: Object, employees: Array, components: Array, filters: Object, largeThreshold: Number, types: Object })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const f = reactive({ status: props.filters.status ?? '', type: props.filters.type ?? '', search: props.filters.search ?? '' })
const apply = () => router.get(route('inputs.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true })
const now = new Date()
const open = ref(false)
const form = useForm({ employee_id: '', type: 'overtime', period_year: now.getFullYear(), period_month: now.getMonth() + 1, pay_component_id: '', description: '', work_date: '', day_type: 'weekday', hours: '', amount: '' })
const isOT = computed(() => form.type === 'overtime')
const submit = () => form.post(route('inputs.store'), { preserveScroll: true, onSuccess: () => { open.value = false; form.reset('description', 'hours', 'amount', 'work_date') } })
const note = ref({})
const decide = (i, d) => {
  if (d === 'rejected' && !(note.value[i.id] || '').trim()) return alert('Isi alasan penolakan di kolom catatan.')
  router.post(route('inputs.decide', i.id), { decision: d, note: note.value[i.id] ?? '' }, { preserveScroll: true })
}
const cancel = (i) => router.post(route('inputs.cancel', i.id), {}, { preserveScroll: true })
</script>

<template>
  <AppLayout>
    <Head title="Input Variabel" />
    <template #title>Input Variabel</template>
    <template #actions><button v-if="can('inputs.create')" class="btn-primary btn-sm" @click="open = !open">+ Input baru</button></template>

    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-ink-300">Lembur, bonus, tunjangan insidentil, reimburse, potongan, dan koreksi. Setiap input <b>harus disetujui orang lain</b> (bukan pengaju, bukan orang yang bersangkutan). Upah lembur <b>dihitung sistem</b> dari jam &amp; upah kontrak, lalu dicocokkan dengan absensi SDM.</p>

    <form v-if="open" class="card card-body mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submit">
      <div class="lg:col-span-2"><label class="label">Karyawan</label><select v-model="form.employee_id" class="input"><option value="">— pilih —</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.nip }} · {{ e.full_name }}</option></select><p v-if="form.errors.employee_id" class="text-xs text-stamp-600">{{ form.errors.employee_id }}</p></div>
      <div><label class="label">Jenis</label><select v-model="form.type" class="input"><option v-for="(l, k) in types" :key="k" :value="k">{{ l }}</option></select></div>
      <div class="grid grid-cols-2 gap-2"><div><label class="label">Tahun</label><input v-model="form.period_year" type="number" class="input num" /></div><div><label class="label">Bulan</label><input v-model="form.period_month" type="number" min="1" max="12" class="input num" /></div></div>
      <template v-if="isOT">
        <div><label class="label">Tanggal lembur</label><input v-model="form.work_date" type="date" class="input" /></div>
        <div><label class="label">Jenis hari</label><select v-model="form.day_type" class="input"><option value="weekday">Hari kerja</option><option value="restday">Hari istirahat</option><option value="holiday">Hari libur</option></select></div>
        <div><label class="label">Jam lembur</label><input v-model="form.hours" type="number" step="0.25" class="input num" /><p v-if="form.errors.hours" class="text-xs text-stamp-600">{{ form.errors.hours }}</p></div>
      </template>
      <template v-else>
        <div><label class="label">Nominal (Rp)</label><input v-model="form.amount" type="number" class="input num" /><p v-if="form.errors.amount" class="text-xs text-stamp-600">{{ form.errors.amount }}</p>
          <p v-if="Number(form.amount) >= largeThreshold" class="text-xs text-amber-700">Nominal besar: butuh penyetuju level Keuangan.</p></div>
        <div><label class="label">Komponen (opsional)</label><select v-model="form.pay_component_id" class="input"><option value="">—</option><option v-for="c in components" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
      </template>
      <div class="sm:col-span-2 lg:col-span-3"><label class="label">Keterangan / dasar (nomor SPL, bukti)</label><input v-model="form.description" class="input" /><p v-if="form.errors.description" class="text-xs text-stamp-600">{{ form.errors.description }}</p></div>
      <div class="flex items-end"><button class="btn-primary w-full" :disabled="form.processing">Ajukan</button></div>
    </form>

    <div class="mb-4 flex flex-wrap gap-2">
      <select v-model="f.status" class="input !w-44" @change="apply"><option value="">Semua status</option><option value="pending">Menunggu</option><option value="approved">Disetujui</option><option value="applied">Dipakai</option><option value="rejected">Ditolak</option></select>
      <select v-model="f.type" class="input !w-44" @change="apply"><option value="">Semua jenis</option><option v-for="(l, k) in types" :key="k" :value="k">{{ l }}</option></select>
      <input v-model="f.search" class="input !w-52" placeholder="Cari karyawan…" @keyup.enter="apply" />
    </div>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Karyawan</th><th>Jenis</th><th>Periode</th><th>Detail</th><th class="text-right">Nominal</th><th>Pengaju</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
        <tbody>
          <tr v-for="i in inputs.data" :key="i.id">
            <td>{{ i.employee }}<p class="font-mono text-xs text-ink-400">{{ i.nip }}</p></td><td>{{ types[i.type] }}</td><td class="num text-xs">{{ i.period }}</td>
            <td class="max-w-xs text-xs">{{ i.description }}<template v-if="i.type === 'overtime'"><br>{{ i.work_date }} · {{ i.hours }} jam
              <span v-if="i.check" :class="i.check.ok === false ? 'text-stamp-600' : i.check.ok ? 'text-seal-700' : 'text-amber-700'" class="block">{{ i.check.ok === null ? '⚠ ' : i.check.ok ? '✓ ' : '✕ ' }}{{ i.check.message }}</span></template>
              <span v-if="i.note" class="block italic text-ink-400">“{{ i.note }}”</span></td>
            <td class="text-right"><Money v-if="i.type !== 'overtime'" :value="i.amount" /><span v-else class="text-xs text-ink-400">dihitung sistem</span></td>
            <td class="text-xs">{{ i.requested_by }}</td><td><StatusBadge :status="i.status" /><p v-if="i.approved_by" class="text-[10px] text-ink-400">{{ i.approved_by }}</p></td>
            <td class="text-right"><div v-if="i.status === 'pending'" class="flex items-center justify-end gap-1">
              <input v-if="can('inputs.approve')" v-model="note[i.id]" class="input !w-28 !py-1 text-xs" placeholder="catatan" />
              <button v-if="can('inputs.approve')" class="btn-accent btn-sm" @click="decide(i, 'approved')">✓</button><button v-if="can('inputs.approve')" class="btn-danger btn-sm" @click="decide(i, 'rejected')">✕</button>
              <button v-if="can('inputs.create')" class="btn-ghost btn-sm" @click="cancel(i)">batal</button></div></td>
          </tr>
          <tr v-if="!inputs.data.length"><td colspan="8" class="py-10 text-center text-ink-400">Belum ada input.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="inputs.links" />
  </AppLayout>
</template>
