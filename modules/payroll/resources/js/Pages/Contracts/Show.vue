<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import ControlRail from '@/Components/ControlRail.vue'
import Money from '@/Components/Money.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({ contract: Object, previous: Object })
const perms = () => usePage().props.auth.user.permissions ?? []
const note = ref('')
const steps = () => props.contract.approvals.map((a) => ({ label: a.label, who: a.by, at: a.at, state: a.status === 'approved' ? 'done' : a.status === 'rejected' ? 'rejected' : 'todo' }))
const railSteps = () => { let cur = false; return steps().map((s) => { if (s.state === 'todo' && !cur && ['proposed', 'verified'].includes(props.contract.status)) { cur = true; return { ...s, state: 'current' } } return s }) }
const nextPending = () => props.contract.approvals.find((a) => a.status === 'pending')
const canAct = () => nextPending() && perms().includes(nextPending().permission)
function decide(decision) {
  if (decision === 'rejected' && note.value.trim().length < 5) return alert('Isi alasan penolakan.')
  router.post(route('contracts.decide', props.contract.id), { decision, note: note.value }, { preserveScroll: true, onSuccess: () => (note.value = '') })
}
const delta = () => (props.previous ? props.contract.base_salary - props.previous.base_salary : null)
</script>

<template>
  <AppLayout>
    <Head :title="contract.number" />
    <template #title>{{ contract.number }} <StatusBadge :status="contract.status" class="ml-2 align-middle" /></template>

    <div v-if="!contract.intact" class="mb-4 rounded-md border border-stamp-400 bg-stamp-50 px-4 py-3 text-sm font-semibold text-stamp-800">⚠ Isi kontrak tidak cocok dengan hash yang dicatat saat pengajuan. Jangan disetujui; laporkan ke Auditor.</div>
    <ControlRail class="mb-6" :steps="railSteps()" />

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="card card-body lg:col-span-2">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <div><Link :href="route('employees.show', contract.employee.id)" class="text-lg font-semibold hover:underline">{{ contract.employee.name }}</Link><p class="font-mono text-xs text-ink-400">{{ contract.employee.nip }} · {{ contract.employee.department }}</p></div>
          <div class="text-right"><p class="text-xs text-ink-400">Gaji pokok</p><p class="text-2xl font-semibold"><Money :value="contract.base_salary" /></p>
            <p v-if="delta() !== null" class="text-xs" :class="delta() > 0 ? 'text-stamp-600' : 'text-seal-600'">{{ delta() > 0 ? '▲' : '▼' }} <Money :value="Math.abs(delta())" /> dari versi sebelumnya</p></div>
        </div>
        <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
          <div><dt class="text-xs text-ink-400">Berlaku</dt><dd class="num">{{ contract.effective_date }} → {{ contract.end_date ?? '…' }}</dd></div>
          <div><dt class="text-xs text-ink-400">Jenis</dt><dd>{{ contract.contract_type.toUpperCase() }}</dd></div>
          <div><dt class="text-xs text-ink-400">Golongan</dt><dd>{{ contract.grade ?? '—' }}</dd></div>
          <div><dt class="text-xs text-ink-400">BPJS Kes / TK</dt><dd>{{ contract.bpjs_kes ? 'Ya' : 'Tidak' }} / {{ contract.bpjs_tk ? 'Ya' : 'Tidak' }} (JKK kls {{ contract.jkk_class }})</dd></div>
          <div><dt class="text-xs text-ink-400">Pajak</dt><dd>{{ contract.tax_scheme === 'ter' ? 'TER / Pasal 17' : 'Tidak dipotong' }}</dd></div>
          <div><dt class="text-xs text-ink-400">THR · Lembur</dt><dd>{{ contract.thr ? 'Ya' : 'Tidak' }} · {{ contract.overtime ? 'Ya' : 'Tidak' }}</dd></div>
          <div><dt class="text-xs text-ink-400">Diusulkan oleh</dt><dd>{{ contract.proposed_by }} <span class="badge-slate">{{ contract.source === 'sdm' ? 'SDM' : 'Payroll' }}</span></dd></div>
          <div><dt class="text-xs text-ink-400">Dokumen</dt><dd class="font-mono text-xs">{{ contract.document_ref ?? '—' }}</dd></div>
          <div><dt class="text-xs text-ink-400">Gaji di SDM</dt><dd><Money :value="contract.employee.sdm_base_salary" /></dd></div>
        </dl>
        <p v-if="contract.in_band === false" class="mt-4 rounded bg-stamp-50 px-3 py-2 text-sm text-stamp-800">Di luar rentang golongan (<Money :value="contract.band[0]" /> – <Money :value="contract.band[1]" />): butuh persetujuan Direksi.</p>
        <p class="mt-4 text-sm"><span class="text-xs text-ink-400">Alasan:</span> {{ contract.reason }}</p>
        <p v-if="contract.rejection_note" class="mt-2 text-sm text-stamp-700">Ditolak: {{ contract.rejection_note }}</p>

        <h3 class="mt-6 text-sm font-semibold">Komponen tetap</h3>
        <table class="data-table mt-1"><tbody>
          <tr v-for="c in contract.components" :key="c.name"><td>{{ c.name }}</td><td class="text-right"><Money :value="c.type === 'deduction' ? -c.amount : c.amount" /></td></tr>
          <tr v-if="!contract.components.length"><td class="text-ink-400">Tidak ada komponen tambahan.</td><td></td></tr></tbody></table>
        <p class="mt-4 break-all font-mono text-[10px] text-ink-400">hash isi: {{ contract.terms_hash }}</p>
      </section>

      <aside class="space-y-6">
        <section class="card card-body">
          <h2 class="mb-3 text-base font-semibold">Keputusan</h2>
          <template v-if="canAct()">
            <p class="mb-2 text-sm">Langkah: <b>{{ nextPending().label }}</b></p>
            <textarea v-model="note" rows="3" class="input" placeholder="Catatan (wajib bila menolak)" />
            <div class="mt-3 flex gap-2"><button class="btn-accent flex-1" @click="decide('approved')">Setujui</button><button class="btn-danger" @click="decide('rejected')">Tolak</button></div>
            <p class="mt-2 text-xs text-ink-500">Sistem menolak bila kamu pengusul, atau sudah memegang langkah lain pada kontrak ini.</p>
          </template>
          <p v-else class="text-sm text-ink-500">{{ nextPending() ? 'Menunggu: ' + nextPending().label : 'Tidak ada keputusan yang menunggu.' }}</p>
        </section>
        <section class="card card-body text-sm">
          <h2 class="mb-2 text-base font-semibold">Jejak persetujuan</h2>
          <ul class="space-y-2"><li v-for="a in contract.approvals" :key="a.step"><p class="font-medium">{{ a.step }}. {{ a.label }} <StatusBadge :status="a.status" /></p><p v-if="a.by" class="text-xs text-ink-500">{{ a.by }} · {{ a.at }}</p><p v-if="a.note" class="text-xs italic">“{{ a.note }}”</p></li></ul>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
