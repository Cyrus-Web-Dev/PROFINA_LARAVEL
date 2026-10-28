<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import Money from '@/Components/Money.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'

defineProps({ employee: Object, contracts: Array, payslips: Array })
const can = (p) => (usePage().props.auth.user.permissions ?? []).includes(p)
</script>

<template>
  <AppLayout>
    <Head :title="employee.full_name" />
    <template #title>{{ employee.full_name }}</template>
    <template #actions><Link v-if="can('contracts.propose')" :href="route('contracts.create', { employee: employee.id })" class="btn-primary btn-sm">Usulkan kontrak baru</Link></template>

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="card card-body lg:col-span-1">
        <dl class="grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
          <div><dt class="text-xs text-ink-400">NIP</dt><dd class="font-mono">{{ employee.nip }}</dd></div>
          <div><dt class="text-xs text-ink-400">Status</dt><dd>{{ employee.status }}</dd></div>
          <div class="col-span-2"><dt class="text-xs text-ink-400">Departemen · Jabatan</dt><dd>{{ employee.department ?? '—' }} · {{ employee.position ?? '—' }}</dd></div>
          <div><dt class="text-xs text-ink-400">Bergabung</dt><dd class="num">{{ employee.join_date ?? '—' }}</dd></div>
          <div><dt class="text-xs text-ink-400">Resign</dt><dd class="num">{{ employee.resign_date ?? '—' }}</dd></div>
          <div><dt class="text-xs text-ink-400">PTKP</dt><dd>{{ employee.ptkp }}</dd></div>
          <div><dt class="text-xs text-ink-400">NPWP</dt><dd>{{ employee.has_npwp ? 'Ada' : 'Tidak ada (+20%)' }}</dd></div>
          <div class="col-span-2"><dt class="text-xs text-ink-400">Rekening</dt><dd class="font-mono text-xs">{{ employee.bank ?? '—' }}</dd>
            <p v-if="employee.bank_changed_at" class="mt-1 text-xs text-stamp-600">Rekening berubah {{ employee.bank_changed_at }} — wajib diverifikasi sebelum gaji berikutnya.</p></div>
          <div v-if="employee.sdm_base_salary != null" class="col-span-2"><dt class="text-xs text-ink-400">Gaji pokok menurut SDM (pembanding)</dt><dd><Money :value="employee.sdm_base_salary" /></dd></div>
        </dl>
        <p class="mt-4 border-t border-ink-100 pt-3 text-xs text-ink-400 dark:border-ink-800">Disalin dari SDM {{ employee.synced_at }}. Data kepegawaian diubah di SDM, bukan di sini.</p>
      </section>

      <div class="space-y-6 lg:col-span-2">
        <section class="card">
          <header class="card-header"><h2 class="text-base font-semibold">Riwayat kontrak kompensasi</h2></header>
          <table class="data-table">
            <thead><tr><th>No.</th><th>Ver.</th><th>Berlaku</th><th>Status</th><th class="text-right">Gaji pokok</th></tr></thead>
            <tbody>
              <tr v-for="c in contracts" :key="c.id"><td><Link :href="route('contracts.show', c.id)" class="font-mono text-xs hover:underline">{{ c.number }}</Link></td><td>v{{ c.version }}</td>
                <td class="num text-xs">{{ c.effective_date }} → {{ c.end_date ?? '…' }}</td><td><StatusBadge :status="c.status" /></td><td class="text-right"><Money :value="c.base_salary" /></td></tr>
              <tr v-if="!contracts.length"><td colspan="5" class="py-8 text-center text-ink-400">Belum ada kontrak (atau kamu tidak berwenang melihatnya).</td></tr>
            </tbody>
          </table>
        </section>
        <section v-if="payslips.length" class="card">
          <header class="card-header"><h2 class="text-base font-semibold">Putaran terakhir</h2></header>
          <table class="data-table"><thead><tr><th>Putaran</th><th class="text-right">Bruto</th><th class="text-right">PPh 21</th><th class="text-right">Bersih</th><th></th></tr></thead>
            <tbody><tr v-for="p in payslips" :key="p.id"><td><Link :href="route('runs.show', p.run_id)" class="hover:underline">{{ p.run }}</Link></td><td class="text-right"><Money :value="p.gross" /></td><td class="text-right"><Money :value="p.pph21" /></td><td class="text-right"><Money :value="p.net" /></td>
              <td class="text-right"><a :href="route('payslips.pdf', p.id)" target="_blank" class="text-xs text-seal-600 underline">PDF</a></td></tr></tbody></table>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
