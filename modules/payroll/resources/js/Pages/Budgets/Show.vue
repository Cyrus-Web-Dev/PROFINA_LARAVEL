<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { MONTHS } from '@/fmt.js'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({ plan: Object, lines: Array, departments: Array, categories: Object, can: Object })
const grid = ref({})
const key = (d, c) => `${d}|${c}`
for (const l of props.lines) { (grid.value[key(l.department_id, l.category)] ??= Array(12).fill(0))[l.month - 1] = Number(l.amount) }
const addDept = ref(props.departments[0]?.id ?? ''); const addCat = ref('basic')
const add = () => { const k = key(addDept.value, addCat.value); if (!grid.value[k]) grid.value[k] = Array(12).fill(0) }
const dname = (id) => props.departments.find((d) => d.id === Number(id))?.name ?? id
const rows = computed(() => Object.keys(grid.value).sort())
const total = computed(() => Object.values(grid.value).reduce((a, r) => a + r.reduce((x, y) => x + Number(y || 0), 0), 0))
const monthTotals = computed(() => Array.from({ length: 12 }, (_, m) => Object.values(grid.value).reduce((a, r) => a + Number(r[m] || 0), 0)))
const fill = (k) => { const v = grid.value[k][0]; grid.value[k] = Array(12).fill(v) }
function save() {
  const lines = []
  for (const k of Object.keys(grid.value)) { const [d, c] = k.split('|'); grid.value[k].forEach((a, i) => { if (Number(a) > 0) lines.push({ department_id: Number(d), month: i + 1, category: c, amount: Number(a) }) }) }
  router.put(route('budgets.lines', props.plan.id), { lines }, { preserveScroll: true })
}
const note = ref('')
const decide = (d) => { if (d === 'rejected' && !note.value.trim()) return alert('Isi alasan penolakan.'); router.post(route('budgets.decide', props.plan.id), { decision: d, note: note.value }, { preserveScroll: true }) }
</script>

<template>
  <AppLayout>
    <Head :title="plan.name" />
    <template #title>{{ plan.name }} <StatusBadge :status="plan.status" class="ml-2 align-middle" /></template>
    <template #actions>
      <button v-if="can.edit" class="btn-secondary btn-sm" @click="save">Simpan</button>
      <button v-if="can.submit" class="btn-primary btn-sm" @click="router.post(route('budgets.submit', plan.id), {}, { preserveScroll: true })">Ajukan persetujuan</button>
      <button v-if="can.version" class="btn-secondary btn-sm" @click="router.post(route('budgets.version', plan.id))">Buat versi revisi</button>
    </template>

    <p v-if="plan.rejection_note" class="mb-4 rounded-md border border-stamp-300 bg-stamp-50 px-4 py-2 text-sm text-stamp-800">Ditolak: {{ plan.rejection_note }}</p>
    <p v-if="plan.status === 'active'" class="mb-4 rounded-md border border-seal-300 bg-seal-50 px-4 py-2 text-sm text-seal-900">Anggaran aktif. Putaran gaji dicek terhadapnya, dan ringkasannya sudah dikirim ke Keuangan lewat webhook <code>budget.approved</code>.</p>

    <div v-if="can.decide" class="card card-body mb-6 flex flex-wrap items-center gap-3"><b class="text-sm">Persetujuan anggaran:</b><input v-model="note" class="input flex-1" placeholder="Catatan (wajib bila menolak)" /><button class="btn-accent" @click="decide('approved')">Setujui & aktifkan</button><button class="btn-danger" @click="decide('rejected')">Tolak</button></div>

    <section class="card overflow-x-auto">
      <div v-if="can.edit" class="flex flex-wrap items-center gap-2 border-b border-ink-100 p-3 dark:border-ink-800"><select v-model="addDept" class="input !w-52"><option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option></select>
        <select v-model="addCat" class="input !w-44"><option v-for="(l, k) in categories" :key="k" :value="k">{{ l }}</option></select><button class="btn-secondary btn-sm" @click="add">+ Tambah baris</button><span class="ml-auto text-xs text-ink-500">Total <Money :value="total" /></span></div>
      <table class="data-table min-w-[1100px]"><thead><tr><th>Departemen · Kategori</th><th v-for="m in MONTHS" :key="m" class="text-right">{{ m.slice(0, 3) }}</th><th class="text-right">Total</th></tr></thead>
        <tbody><tr v-for="k in rows" :key="k"><td class="whitespace-nowrap text-sm">{{ dname(k.split('|')[0]) }} · <span class="text-ink-500">{{ categories[k.split('|')[1]] }}</span><button v-if="can.edit" class="ml-2 text-[10px] text-seal-600 underline" title="Salin bulan Januari ke semua bulan" @click="fill(k)">isi rata</button></td>
          <td v-for="(v, i) in grid[k]" :key="i" class="p-1"><input v-if="can.edit" v-model.number="grid[k][i]" type="number" min="0" class="input num !w-24 !px-2 !py-1 text-right text-xs" /><span v-else class="num block text-right text-xs">{{ Number(v).toLocaleString('id-ID') }}</span></td>
          <td class="num text-right text-xs font-semibold">{{ grid[k].reduce((a, b) => a + Number(b || 0), 0).toLocaleString('id-ID') }}</td></tr>
          <tr class="font-semibold"><td>Total</td><td v-for="(v, i) in monthTotals" :key="i" class="num text-right text-xs">{{ v.toLocaleString('id-ID') }}</td><td class="num text-right text-xs">{{ total.toLocaleString('id-ID') }}</td></tr></tbody></table>
    </section>
    <ul v-if="plan.steps.length" class="mt-4 text-sm"><li v-for="(s, i) in plan.steps" :key="i"><b>{{ s.label }}</b> <StatusBadge :status="s.status" /> <span v-if="s.by" class="text-xs text-ink-500">{{ s.by }} · {{ s.at }}</span></li></ul>
  </AppLayout>
</template>
