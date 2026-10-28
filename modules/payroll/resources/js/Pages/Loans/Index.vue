<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'

defineProps({ loans: Object, employees: Array, filters: Object })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
const now = new Date()
const open = ref(false)
const form = useForm({ employee_id: '', principal: '', installments: 6, start_year: now.getFullYear(), start_month: now.getMonth() + 1, reason: '' })
const submit = () => form.post(route('loans.store'), { preserveScroll: true, onSuccess: () => { open.value = false; form.reset() } })
const decide = (l, d) => router.post(route('loans.decide', l.id), { decision: d }, { preserveScroll: true })
</script>

<template>
  <AppLayout>
    <Head title="Kasbon" />
    <template #title>Kasbon Karyawan</template>
    <template #actions><button v-if="can('loans.manage')" class="btn-primary btn-sm" @click="open = !open">+ Kasbon</button></template>
    <p class="mb-4 max-w-3xl text-sm text-ink-600 dark:text-ink-300">Cicilan dipotong otomatis tiap putaran gaji bulanan, maksimal 50% dari penghasilan setelah BPJS &amp; pajak. Jurnalnya mengurangi <i>Piutang Karyawan</i> di Keuangan.</p>
    <form v-if="open" class="card card-body mb-6 grid gap-3 sm:grid-cols-3" @submit.prevent="submit">
      <div class="sm:col-span-2"><label class="label">Karyawan</label><select v-model="form.employee_id" class="input"><option value="">— pilih —</option><option v-for="e in employees" :key="e.id" :value="e.id">{{ e.nip }} · {{ e.full_name }}</option></select></div>
      <div><label class="label">Pokok (Rp)</label><input v-model="form.principal" type="number" class="input num" /><p v-if="form.errors.principal" class="text-xs text-stamp-600">{{ form.errors.principal }}</p></div>
      <div><label class="label">Jumlah cicilan</label><input v-model="form.installments" type="number" min="1" max="60" class="input num" /></div>
      <div class="grid grid-cols-2 gap-2"><div><label class="label">Mulai tahun</label><input v-model="form.start_year" type="number" class="input num" /></div><div><label class="label">Bulan</label><input v-model="form.start_month" type="number" min="1" max="12" class="input num" /></div></div>
      <div><label class="label">Alasan</label><input v-model="form.reason" class="input" /></div>
      <div class="sm:col-span-3"><button class="btn-primary" :disabled="form.processing">Ajukan kasbon</button></div>
    </form>
    <div class="card overflow-x-auto"><table class="data-table">
      <thead><tr><th>No.</th><th>Karyawan</th><th class="text-right">Pokok</th><th class="text-right">Cicilan</th><th>Progres</th><th class="text-right">Sisa</th><th>Status</th><th></th></tr></thead>
      <tbody><tr v-for="l in loans.data" :key="l.id"><td class="font-mono text-xs">{{ l.number }}</td><td>{{ l.employee }}<p class="text-xs text-ink-400">mulai {{ l.start }} · {{ l.reason }}</p></td><td class="text-right"><Money :value="l.principal" /></td><td class="text-right"><Money :value="l.installment_amount" /></td>
        <td class="num text-xs">{{ l.paid }}/{{ l.installments }}</td><td class="text-right"><Money :value="l.remaining" /></td><td><StatusBadge :status="l.status === 'active' ? 'active' : l.status" /></td>
        <td class="text-right"><template v-if="l.status === 'pending' && can('loans.approve')"><button class="btn-accent btn-sm mr-1" @click="decide(l, 'approved')">Setujui</button><button class="btn-danger btn-sm" @click="decide(l, 'rejected')">Tolak</button></template></td></tr>
        <tr v-if="!loans.data.length"><td colspan="8" class="py-10 text-center text-ink-400">Belum ada kasbon.</td></tr></tbody></table></div>
    <Pagination :links="loans.links" />
  </AppLayout>
</template>
