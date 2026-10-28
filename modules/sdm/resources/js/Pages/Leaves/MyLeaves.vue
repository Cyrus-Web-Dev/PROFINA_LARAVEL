<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'

const props = defineProps({ leaves: Array, leaveTypes: Array, balances: { type: Array, default: () => [] }, upcomingHolidays: { type: Array, default: () => [] } })

const selectedType = () => props.leaveTypes.find(t => t.id === Number(form.leave_type_id))
function fmtDate(d) { return new Date(d).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }

const showForm = ref(false)
const form = useForm({ leave_type_id: '', start_date: '', end_date: '', reason: '', attachment: null })

function submit() {
  form.post(route('leaves.store'), { forceFormData: true, onSuccess: () => { form.reset(); showForm.value = false } })
}
function cancel(leave) {
  if (confirm('Batalkan pengajuan cuti ini?')) router.post(route('leaves.cancel', leave.id))
}

const statusBadge = { pending: 'badge-amber', approved: 'badge-green', rejected: 'badge-red', cancelled: 'badge-slate' }
const statusLabels = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }
</script>

<template>
  <AppLayout>
    <Head title="Cuti Saya" />
    <template #title>Cuti Saya</template>
    <template #actions>
      <button @click="showForm = !showForm" class="btn-accent btn-sm">{{ showForm ? 'Batal' : '+ Ajukan Cuti' }}</button>
    </template>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
      <div v-for="b in balances" :key="b.leave_type_id" class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Sisa {{ b.name }}</p>
        <p class="mt-2 font-mono text-3xl font-bold text-ink-900 dark:text-ink-50">{{ b.remaining }}<span class="text-base font-normal text-ink-400"> / {{ b.quota }} hari</span></p>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-100 dark:bg-ink-800"><div class="h-full bg-seal-500" :style="{ width: Math.min(100, (b.used / b.quota) * 100) + '%' }"></div></div>
      </div>
      <div class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">Libur Mendatang</p>
        <ul class="mt-2 space-y-1.5 text-sm">
          <li v-for="h in upcomingHolidays" :key="h.date" class="flex justify-between gap-3"><span class="text-ink-700 dark:text-ink-200">{{ h.name }}</span><span class="shrink-0 font-mono text-xs text-ink-400">{{ fmtDate(h.date) }}</span></li>
          <li v-if="!upcomingHolidays.length" class="text-ink-400">-</li>
        </ul>
      </div>
    </div>

    <form v-if="showForm" @submit.prevent="submit" class="card card-body mb-6 space-y-4">
      <p class="rounded-md bg-ink-50 px-3 py-2 text-xs text-ink-500 dark:bg-ink-800/50 dark:text-ink-400">Lama cuti dihitung dalam <strong>hari kerja</strong> — akhir pekan dan hari libur nasional/perusahaan tidak mengurangi jatah cuti.</p>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Jenis Cuti</label>
          <select v-model="form.leave_type_id" class="input" required>
            <option value="">Pilih jenis cuti</option>
            <option v-for="t in leaveTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <p v-if="form.errors.leave_type_id" class="error-text">{{ form.errors.leave_type_id }}</p>
        </div>
        <div></div>
        <div>
          <label class="label">Tanggal Mulai</label>
          <input v-model="form.start_date" type="date" class="input" required />
          <p v-if="form.errors.start_date" class="error-text">{{ form.errors.start_date }}</p>
        </div>
        <div>
          <label class="label">Tanggal Selesai</label>
          <input v-model="form.end_date" type="date" class="input" required />
          <p v-if="form.errors.end_date" class="error-text">{{ form.errors.end_date }}</p>
        </div>
        <div class="sm:col-span-2">
          <label class="label">Alasan</label>
          <textarea v-model="form.reason" rows="3" class="input" required></textarea>
          <p v-if="form.errors.reason" class="error-text">{{ form.errors.reason }}</p>
        </div>
        <div class="sm:col-span-2">
          <label class="label">Lampiran <span v-if="selectedType()?.requires_attachment" class="text-red-600">* wajib untuk jenis ini (mis. surat dokter)</span><span v-else>(opsional)</span></label>
          <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="input" @change="form.attachment = $event.target.files[0]" />
          <p v-if="form.errors.attachment" class="error-text">{{ form.errors.attachment }}</p>
        </div>
      </div>
      <button type="submit" :disabled="form.processing" class="btn-accent">Kirim Pengajuan</button>
    </form>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Jenis</th><th>Periode</th><th>Hari</th><th>Status</th><th>Keterangan</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
            <tr v-for="l in leaves" :key="l.id">
              <td>{{ l.leave_type.name }}</td>
              <td class="num">{{ l.start_date }} — {{ l.end_date }}</td>
              <td class="num">{{ l.total_days }}</td>
              <td><span :class="statusBadge[l.status]">{{ statusLabels[l.status] }}</span></td>
              <td class="text-ink-500 text-xs">{{ l.rejection_reason || '-' }}</td>
              <td class="text-right">
                <button v-if="l.status === 'pending'" @click="cancel(l)" class="btn-ghost btn-sm text-red-600">Batalkan</button>
              </td>
            </tr>
            <tr v-if="!leaves.length"><td colspan="6" class="py-10 text-center text-sm text-ink-400">Belum ada pengajuan cuti.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
