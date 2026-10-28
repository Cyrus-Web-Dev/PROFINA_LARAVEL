<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'

const props = defineProps({
  employee: Object,
  departments: Array,
  positions: Array,
  supervisors: Array,
})

const isEdit = computed(() => !!props.employee)

const form = useForm({
  nip: props.employee?.nip ?? '',
  full_name: props.employee?.full_name ?? '',
  gender: props.employee?.gender ?? 'L',
  place_of_birth: props.employee?.place_of_birth ?? '',
  date_of_birth: props.employee?.date_of_birth ?? '',
  address: props.employee?.address ?? '',
  phone: props.employee?.phone ?? '',
  personal_email: props.employee?.personal_email ?? '',
  marital_status: props.employee?.marital_status ?? 'single',
  dependents_count: props.employee?.dependents_count ?? 0,

  department_id: props.employee?.department_id ?? '',
  position_id: props.employee?.position_id ?? '',
  supervisor_id: props.employee?.supervisor_id ?? '',

  employment_type: props.employee?.employment_type ?? 'kontrak',
  join_date: props.employee?.join_date ?? '',
  contract_end_date: props.employee?.contract_end_date ?? '',
  resign_date: props.employee?.resign_date ?? '',
  employment_status: props.employee?.employment_status ?? 'active',

  base_salary: props.employee?.base_salary ?? 0,
  bank_name: props.employee?.bank_name ?? '',
  bank_account_number: props.employee?.bank_account_number ?? '',
  bank_account_name: props.employee?.bank_account_name ?? '',
  npwp: props.employee?.npwp ?? '',
  bpjs_kesehatan_number: props.employee?.bpjs_kesehatan_number ?? '',
  bpjs_tk_number: props.employee?.bpjs_tk_number ?? '',
  ptkp_status: props.employee?.ptkp_status ?? 'TK/0',

  photo: null,
  emergency_contact_name: props.employee?.emergency_contact_name ?? '',
  emergency_contact_phone: props.employee?.emergency_contact_phone ?? '',
  notes: props.employee?.notes ?? '',

  create_account: false,
  account_email: '',
})

const photoPreview = ref(props.employee?.photo_url ?? null)
function onPhotoChange(e) {
  const file = e.target.files[0]
  form.photo = file
  photoPreview.value = file ? URL.createObjectURL(file) : (props.employee?.photo_url ?? null)
}

const filteredPositions = computed(() =>
  form.department_id ? props.positions.filter(p => String(p.department_id) === String(form.department_id)) : props.positions
)

function submit() {
  if (isEdit.value) {
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('employees.update', props.employee.id), {
      forceFormData: true,
    })
  } else {
    form.post(route('employees.store'), { forceFormData: true })
  }
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Ubah Karyawan' : 'Tambah Karyawan'" />
    <template #title>{{ isEdit ? 'Ubah Data Karyawan' : 'Tambah Karyawan Baru' }}</template>

    <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
      <div class="card card-body flex items-center gap-5">
        <img v-if="photoPreview" :src="photoPreview" class="h-20 w-20 rounded-full object-cover border border-ink-200 dark:border-ink-700" />
        <div v-else class="flex h-20 w-20 items-center justify-center rounded-full bg-ink-100 text-ink-400 dark:bg-ink-800">Foto</div>
        <div>
          <label class="btn-secondary btn-sm cursor-pointer">
            Pilih Foto
            <input type="file" accept="image/*" class="hidden" @change="onPhotoChange" />
          </label>
          <p class="mt-1 text-xs text-ink-400">JPG/PNG, maks. 2MB.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="font-serif font-semibold">Data Pribadi</h2></div>
        <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">NIP *</label>
            <input v-model="form.nip" class="input" required />
            <p v-if="form.errors.nip" class="error-text">{{ form.errors.nip }}</p>
          </div>
          <div>
            <label class="label">Nama Lengkap *</label>
            <input v-model="form.full_name" class="input" required />
            <p v-if="form.errors.full_name" class="error-text">{{ form.errors.full_name }}</p>
          </div>
          <div>
            <label class="label">Jenis Kelamin *</label>
            <select v-model="form.gender" class="input">
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div>
            <label class="label">Status Pernikahan *</label>
            <select v-model="form.marital_status" class="input">
              <option value="single">Belum Menikah</option>
              <option value="married">Menikah</option>
              <option value="divorced">Cerai</option>
              <option value="widowed">Janda/Duda</option>
            </select>
          </div>
          <div>
            <label class="label">Tempat Lahir</label>
            <input v-model="form.place_of_birth" class="input" />
          </div>
          <div>
            <label class="label">Tanggal Lahir</label>
            <input v-model="form.date_of_birth" type="date" class="input" />
          </div>
          <div>
            <label class="label">No. HP</label>
            <input v-model="form.phone" class="input" />
          </div>
          <div>
            <label class="label">Email Pribadi</label>
            <input v-model="form.personal_email" type="email" class="input" />
            <p v-if="form.errors.personal_email" class="error-text">{{ form.errors.personal_email }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="label">Alamat</label>
            <textarea v-model="form.address" rows="2" class="input"></textarea>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="font-serif font-semibold">Kepegawaian</h2></div>
        <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Departemen</label>
            <select v-model="form.department_id" class="input">
              <option value="">—</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Jabatan</label>
            <select v-model="form.position_id" class="input">
              <option value="">—</option>
              <option v-for="p in filteredPositions" :key="p.id" :value="p.id">{{ p.title }}</option>
            </select>
          </div>
          <div>
            <label class="label">Atasan Langsung</label>
            <select v-model="form.supervisor_id" class="input">
              <option value="">—</option>
              <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.full_name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Tipe Kepegawaian *</label>
            <select v-model="form.employment_type" class="input">
              <option value="tetap">Karyawan Tetap (PKWTT)</option>
              <option value="kontrak">Kontrak (PKWT)</option>
              <option value="magang">Magang</option>
              <option value="harian">Harian Lepas</option>
            </select>
          </div>
          <div>
            <label class="label">Tanggal Bergabung *</label>
            <input v-model="form.join_date" type="date" class="input" required />
            <p v-if="form.errors.join_date" class="error-text">{{ form.errors.join_date }}</p>
          </div>
          <div>
            <label class="label">Akhir Kontrak (bila PKWT)</label>
            <input v-model="form.contract_end_date" type="date" class="input" />
          </div>
          <div>
            <label class="label">Status Kepegawaian *</label>
            <select v-model="form.employment_status" class="input">
              <option value="active">Aktif</option>
              <option value="resigned">Resign</option>
              <option value="terminated">Diberhentikan</option>
              <option value="retired">Pensiun</option>
            </select>
          </div>
          <div v-if="isEdit && form.employment_status !== 'active'">
            <label class="label">Tanggal Berhenti</label>
            <input v-model="form.resign_date" type="date" class="input" />
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="font-serif font-semibold">Penggajian &amp; Pajak</h2></div>
        <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Gaji Pokok (Rp) *</label>
            <input v-model.number="form.base_salary" type="number" min="0" step="1000" class="input font-mono" required />
            <p v-if="form.errors.base_salary" class="error-text">{{ form.errors.base_salary }}</p>
          </div>
          <div>
            <label class="label">Status PTKP *</label>
            <select v-model="form.ptkp_status" class="input">
              <option v-for="s in ['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3']" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div>
            <label class="label">Jumlah Tanggungan</label>
            <input v-model.number="form.dependents_count" type="number" min="0" max="20" class="input" />
          </div>
          <div>
            <label class="label">NPWP</label>
            <input v-model="form.npwp" class="input font-mono" />
          </div>
          <div>
            <label class="label">No. BPJS Kesehatan</label>
            <input v-model="form.bpjs_kesehatan_number" class="input font-mono" />
          </div>
          <div>
            <label class="label">No. BPJS Ketenagakerjaan</label>
            <input v-model="form.bpjs_tk_number" class="input font-mono" />
          </div>
          <div>
            <label class="label">Nama Bank</label>
            <input v-model="form.bank_name" class="input" />
          </div>
          <div>
            <label class="label">No. Rekening</label>
            <input v-model="form.bank_account_number" class="input font-mono" />
          </div>
          <div>
            <label class="label">Nama Pemilik Rekening</label>
            <input v-model="form.bank_account_name" class="input" />
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2 class="font-serif font-semibold">Kontak Darurat &amp; Catatan</h2></div>
        <div class="card-body grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Nama Kontak Darurat</label>
            <input v-model="form.emergency_contact_name" class="input" />
          </div>
          <div>
            <label class="label">No. HP Kontak Darurat</label>
            <input v-model="form.emergency_contact_phone" class="input" />
          </div>
          <div class="sm:col-span-2">
            <label class="label">Catatan Internal</label>
            <textarea v-model="form.notes" rows="2" class="input"></textarea>
          </div>
        </div>
      </div>

      <div v-if="!isEdit" class="card">
        <div class="card-header"><h2 class="font-serif font-semibold">Akun Login</h2></div>
        <div class="card-body space-y-3">
          <label class="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
            <input v-model="form.create_account" type="checkbox" class="rounded border-ink-300 text-seal-600 focus:ring-seal-500" />
            Buatkan akun login untuk karyawan ini (self-service: absen, cuti, slip gaji)
          </label>
          <div v-if="form.create_account">
            <label class="label">Email untuk Login *</label>
            <input v-model="form.account_email" type="email" class="input max-w-sm" />
            <p v-if="form.errors.account_email" class="error-text">{{ form.errors.account_email }}</p>
            <p class="mt-1 text-xs text-ink-400">Password sementara akan dibuat otomatis dan dikirim ke email ini.</p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="btn-accent">
          {{ form.processing ? 'Menyimpan…' : (isEdit ? 'Simpan Perubahan' : 'Simpan Karyawan') }}
        </button>
        <Link :href="route('employees.index')" class="btn-secondary">Batal</Link>
      </div>
    </form>
  </AppLayout>
</template>
