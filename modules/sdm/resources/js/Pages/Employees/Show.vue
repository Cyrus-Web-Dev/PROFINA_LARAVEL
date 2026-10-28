<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ employee: Object })
const page = usePage()
const canManage = () => (page.props.auth.user.permissions ?? []).includes('employees.manage')
const canManageDocs = () => (page.props.auth.user.permissions ?? []).includes('documents.manage')

const docTypeLabels = {
  contract: 'Kontrak Kerja', id_card: 'KTP', diploma: 'Ijazah', certificate: 'Sertifikat',
  npwp: 'NPWP', bpjs: 'Kartu BPJS', other: 'Lainnya',
}

const showDocForm = ref(false)
const docForm = useForm({ type: 'contract', title: '', file: null, issued_at: '', expires_at: '' })

function submitDoc() {
  docForm.post(route('documents.store', props.employee.id), {
    forceFormData: true,
    onSuccess: () => { docForm.reset(); showDocForm.value = false },
  })
}

function fmt(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
function fmtCurrency(n) {
  return 'Rp ' + Number(n ?? 0).toLocaleString('id-ID')
}
</script>

<template>
  <AppLayout>
    <Head :title="employee.full_name" />
    <template #title>Detail Karyawan</template>
    <template #actions>
      <Link v-if="canManage()" :href="route('employees.edit', employee.id)" class="btn-accent btn-sm">Ubah Data</Link>
    </template>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="card lg:col-span-1">
        <div class="card-body text-center">
          <UserAvatar :src="employee.photo_url" :name="employee.full_name" :size="88" class="mx-auto" />
          <h2 class="mt-4 font-serif text-lg font-semibold text-ink-900 dark:text-ink-100">{{ employee.full_name }}</h2>
          <p class="text-sm text-ink-500">{{ employee.position?.title || 'Jabatan belum diatur' }}</p>
          <p class="mt-1 text-xs font-mono text-ink-400">{{ employee.nip }}</p>
          <div class="mt-4 flex justify-center gap-2">
            <span class="badge-seal">{{ employee.department?.name || 'Tanpa Departemen' }}</span>
          </div>
        </div>
        <div class="card-header !border-t !border-b-0"><h3 class="text-sm font-semibold text-ink-700 dark:text-ink-300">Kontak</h3></div>
        <div class="card-body space-y-2 text-sm">
          <p><span class="text-ink-400">No. HP:</span> {{ employee.phone || '-' }}</p>
          <p><span class="text-ink-400">Email:</span> {{ employee.personal_email || '-' }}</p>
          <p><span class="text-ink-400">Alamat:</span> {{ employee.address || '-' }}</p>
          <p><span class="text-ink-400">Kontak Darurat:</span> {{ employee.emergency_contact_name || '-' }} ({{ employee.emergency_contact_phone || '-' }})</p>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-6">
        <div class="card">
          <div class="card-header"><h3 class="font-serif font-semibold">Kepegawaian</h3></div>
          <div class="card-body grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div><p class="text-ink-400">Tipe</p><p class="font-medium">{{ employee.employment_type_label }}</p></div>
            <div><p class="text-ink-400">Status</p><p class="font-medium">{{ employee.employment_status_label }}</p></div>
            <div><p class="text-ink-400">Bergabung</p><p class="font-medium num">{{ fmt(employee.join_date) }}</p></div>
            <div><p class="text-ink-400">Atasan</p><p class="font-medium">{{ employee.supervisor?.full_name || '-' }}</p></div>
            <div><p class="text-ink-400">Status Pernikahan</p><p class="font-medium">{{ employee.marital_status }}</p></div>
            <div><p class="text-ink-400">Tanggungan</p><p class="font-medium">{{ employee.dependents_count }}</p></div>
          </div>
        </div>

        <div class="card" v-if="canManage()">
          <div class="card-header"><h3 class="font-serif font-semibold">Penggajian &amp; Pajak</h3></div>
          <div class="card-body grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div><p class="text-ink-400">Gaji Pokok</p><p class="font-mono font-medium">{{ fmtCurrency(employee.base_salary) }}</p></div>
            <div><p class="text-ink-400">PTKP</p><p class="font-mono font-medium">{{ employee.ptkp_status }}</p></div>
            <div><p class="text-ink-400">NPWP</p><p class="font-mono font-medium">{{ employee.npwp || '-' }}</p></div>
            <div><p class="text-ink-400">BPJS Kesehatan</p><p class="font-mono font-medium">{{ employee.bpjs_kesehatan_number || '-' }}</p></div>
            <div><p class="text-ink-400">BPJS Ketenagakerjaan</p><p class="font-mono font-medium">{{ employee.bpjs_tk_number || '-' }}</p></div>
            <div><p class="text-ink-400">Rekening</p><p class="font-mono font-medium">{{ employee.bank_name }} {{ employee.bank_account_number }}</p></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="font-serif font-semibold">Dokumen</h3>
            <button v-if="canManageDocs()" @click="showDocForm = !showDocForm" class="btn-secondary btn-sm">{{ showDocForm ? 'Batal' : '+ Unggah' }}</button>
          </div>
          <form v-if="showDocForm" @submit.prevent="submitDoc" class="card-body space-y-3 border-b border-ink-100 dark:border-ink-800">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="label">Jenis Dokumen</label>
                <select v-model="docForm.type" class="input">
                  <option v-for="(label, key) in docTypeLabels" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
              <div>
                <label class="label">Judul</label>
                <input v-model="docForm.title" class="input" required />
              </div>
              <div>
                <label class="label">Tanggal Terbit</label>
                <input v-model="docForm.issued_at" type="date" class="input" />
              </div>
              <div>
                <label class="label">Tanggal Kedaluwarsa</label>
                <input v-model="docForm.expires_at" type="date" class="input" />
              </div>
              <div class="sm:col-span-2">
                <label class="label">File (PDF/JPG/PNG, maks. 5MB)</label>
                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="input" @change="docForm.file = $event.target.files[0]" required />
              </div>
            </div>
            <button type="submit" :disabled="docForm.processing" class="btn-accent btn-sm">Unggah Dokumen</button>
          </form>
          <ul class="card-body space-y-3">
            <li v-for="doc in employee.documents" :key="doc.id" class="flex items-center justify-between text-sm">
              <div>
                <p class="font-medium text-ink-800 dark:text-ink-100">{{ doc.title }} <span class="badge-slate ml-1">{{ docTypeLabels[doc.type] }}</span></p>
                <p class="text-xs text-ink-400">Kedaluwarsa: {{ fmt(doc.expires_at) }}</p>
              </div>
              <a :href="route('documents.download', doc.id)" class="btn-ghost btn-sm">Unduh</a>
            </li>
            <li v-if="!employee.documents?.length" class="text-sm text-ink-400">Belum ada dokumen diunggah.</li>
          </ul>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
