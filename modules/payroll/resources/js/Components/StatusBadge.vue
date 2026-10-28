<script setup>
import { computed } from 'vue'

// Satu sumber warna status untuk seluruh aplikasi. Merah ("stamp") hanya untuk pengecualian kontrol.
const props = defineProps({ status: String, label: String })

const MAP = {
  // putaran
  draft: ['badge-slate', 'Draf'], calculated: ['badge-slate', 'Sudah dihitung'], in_review: ['badge-amber', 'Menunggu verifikasi'], verified: ['badge-amber', 'Menunggu persetujuan'],
  approved: ['badge-seal', 'Disetujui & terkunci'], funding: ['badge-amber', 'Menunggu dana'], funded: ['badge-seal', 'Dana siap'], disbursing: ['badge-amber', 'Transfer berjalan'],
  paid: ['badge-green', 'Terbayar'], closed: ['badge-green', 'Ditutup'], rejected: ['badge-stamp', 'Dikembalikan'], cancelled: ['badge-slate', 'Dibatalkan'],
  // kontrak
  proposed: ['badge-amber', 'Diusulkan'], active: ['badge-green', 'Aktif'], superseded: ['badge-slate', 'Digantikan'], expired: ['badge-slate', 'Berakhir'],
  // umum
  pending: ['badge-amber', 'Menunggu'], resolved: ['badge-green', 'Selesai'], waived: ['badge-slate', 'Dikecualikan'], open: ['badge-stamp', 'Terbuka'],
  submitted: ['badge-amber', 'Diajukan'], revision: ['badge-amber', 'Perlu revisi'], disbursed: ['badge-green', 'Dicairkan'], confirmed: ['badge-green', 'Terkonfirmasi'],
  delivered: ['badge-green', 'Terkirim'], failed: ['badge-stamp', 'Gagal'], processed: ['badge-green', 'Diproses'], ignored: ['badge-slate', 'Diabaikan'], received: ['badge-amber', 'Diterima'],
  generated: ['badge-seal', 'Dibuat'], reconciled: ['badge-green', 'Cocok'], mismatch: ['badge-stamp', 'Selisih'],
  critical: ['badge-stamp', 'Kritis'], warning: ['badge-amber', 'Peringatan'], info: ['badge-slate', 'Info'],
  applied: ['badge-green', 'Dipakai'], settled: ['badge-green', 'Lunas'],
}
const cfg = computed(() => MAP[props.status] ?? ['badge-slate', props.status])
</script>

<template><span :class="cfg[0]">{{ label ?? cfg[1] }}</span></template>
