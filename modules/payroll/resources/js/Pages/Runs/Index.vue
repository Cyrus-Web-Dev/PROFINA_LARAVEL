<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ runs: Object, filters: Object, defaults: Object, statusLabels: Object })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const open = ref(false)
const form = useForm({ type: 'regular', period_year: props.defaults.year, period_month: props.defaults.month, pay_date: '', thr_holiday_date: '', notes: '' })
const submit = () => form.post(route('runs.store'), { preserveScroll: true })
</script>

<template>
  <AppLayout>
    <Head title="Putaran Gaji" />
    <template #title>Putaran Gaji</template>
    <template #actions><button v-if="can('runs.create')" class="btn-primary btn-sm" @click="open = !open">+ Putaran baru</button></template>

    <form v-if="open" class="card card-body mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="submit">
      <div><label class="label">Jenis</label><select v-model="form.type" class="input"><option value="regular">Gaji bulanan</option><option value="thr">THR</option><option value="bonus">Bonus / insidentil</option><option value="correction">Koreksi</option></select></div>
      <div><label class="label">Tahun</label><input v-model="form.period_year" type="number" class="input num" /></div>
      <div><label class="label">Bulan</label><input v-model="form.period_month" type="number" min="1" max="12" class="input num" /></div>
      <div><label class="label">Tanggal bayar</label><input v-model="form.pay_date" type="date" class="input" /></div>
      <div v-if="form.type === 'thr'"><label class="label">Tanggal hari raya</label><input v-model="form.thr_holiday_date" type="date" class="input" /><p class="text-[10px] text-ink-500">THR wajib dibayar paling lambat H-7.</p></div>
      <div class="flex items-end"><button class="btn-primary w-full" :disabled="form.processing">Buat putaran</button></div>
      <p v-if="Object.keys(form.errors).length" class="text-sm text-stamp-600 sm:col-span-2 lg:col-span-5">{{ Object.values(form.errors)[0] }}</p>
    </form>

    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>No.</th><th>Putaran</th><th>Status</th><th class="text-right">Karyawan</th><th class="text-right">Gaji bersih</th><th class="text-right">Biaya perusahaan</th><th>Tgl bayar</th></tr></thead>
        <tbody>
          <tr v-for="r in runs.data" :key="r.id">
            <td><Link :href="route('runs.show', r.id)" class="font-mono text-xs hover:underline">{{ r.number }}</Link></td>
            <td><Link :href="route('runs.show', r.id)" class="font-medium hover:underline">{{ r.title }}</Link> <span v-if="r.locked" title="Terkunci" class="text-seal-600">🔒</span></td>
            <td><StatusBadge :status="r.status" /></td><td class="num text-right">{{ r.employees }}</td><td class="text-right"><Money :value="r.total_net" /></td><td class="text-right"><Money :value="r.total_employer_cost" /></td><td class="num text-xs">{{ r.pay_date }}</td>
          </tr>
          <tr v-if="!runs.data.length"><td colspan="7" class="py-10 text-center text-ink-400">Belum ada putaran gaji.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="runs.links" />
  </AppLayout>
</template>
