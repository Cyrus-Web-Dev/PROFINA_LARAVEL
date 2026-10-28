<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import UserAvatar from '@/Components/UserAvatar.vue'
import ImageCropper from '@/Components/ImageCropper.vue'
import PhotoViewer from '@/Components/PhotoViewer.vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({ profile: { type: Object, required: true } })

const inputClass = 'mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm'
const labelClass = 'text-xs font-medium text-slate-500 dark:text-slate-400'
const cardClass = 'rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700'

// ---------- Data profil ----------
const form = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    username: props.profile.username ?? '',
    phone: props.profile.phone ?? '',
    employee_id: props.profile.employee_id ?? '',
    job_title: props.profile.job_title ?? '',
    department: props.profile.department ?? '',
    birth_date: props.profile.birth_date ?? '',
    address: props.profile.address ?? '',
    bio: props.profile.bio ?? '',
    education: (props.profile.education ?? []).map((e) => ({
        level: e.level ?? '',
        institution: e.institution ?? '',
        major: e.major ?? '',
        start_year: e.start_year ?? '',
        end_year: e.end_year ?? '',
    })),
    current_password: '',
})

// Mengganti email butuh konfirmasi password (email = tujuan kode OTP login)
const emailChanged = computed(() => form.email.trim().toLowerCase() !== (props.profile.email ?? '').toLowerCase())

const levels = ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3', 'Profesi', 'Lainnya']
function addEducation() {
    form.education.push({ level: 'S1', institution: '', major: '', start_year: '', end_year: '' })
}
function removeEducation(i) {
    form.education.splice(i, 1)
}

function save() {
    // baris pendidikan yang institusinya kosong dibuang supaya tidak memicu error
    form.education = form.education.filter((e) => (e.institution ?? '').trim() !== '')
    form.put(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('current_password'),
    })
}

// ---------- Password ----------
const pwForm = useForm({ current_password: '', password: '', password_confirmation: '' })
function savePassword() {
    pwForm.put(route('profile.password'), {
        preserveScroll: true,
        errorBag: 'updatePassword',
        onSuccess: () => pwForm.reset(),
    })
}

// ---------- Foto profil (crop + zoom) ----------
const fileInput = ref(null)
const pickedFile = ref(null)
const cropOpen = ref(false)
const viewerOpen = ref(false)
const photoError = ref('')
const uploading = ref(false)

function pickFile(e) {
    const f = e.target.files?.[0]
    e.target.value = '' // supaya file yang sama bisa dipilih lagi
    if (!f) return
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) {
        photoError.value = 'Format harus JPG, PNG, atau WebP.'
        return
    }
    if (f.size > 20 * 1024 * 1024) {
        photoError.value = 'File terlalu besar (maksimal 20 MB).'
        return
    }
    photoError.value = ''
    pickedFile.value = f
    cropOpen.value = true
}
function uploadCropped(file) {
    cropOpen.value = false
    uploading.value = true
    router.post(route('profile.photo.upload'), { photo: file }, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errs) => { photoError.value = Object.values(errs)[0] ?? 'Gagal mengunggah foto.' },
        onFinish: () => { uploading.value = false },
    })
}
function removePhoto() {
    if (confirm('Hapus foto profil?')) router.delete(route('profile.photo.delete'), { preserveScroll: true })
}
</script>

<template>
    <AppLayout>
        <template #title>Profil Saya</template>

        <div class="mx-auto max-w-3xl space-y-6">
            <!-- Kartu identitas -->
            <div :class="cardClass">
                <div class="flex flex-col items-center gap-5 sm:flex-row">
                    <div class="relative">
                        <button type="button" :disabled="!profile.avatar_url" class="block rounded-full focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:cursor-default"
                            :title="profile.avatar_url ? 'Klik untuk memperbesar' : ''" @click="viewerOpen = true">
                            <UserAvatar :src="profile.avatar_url" :name="profile.name" :size="112" />
                        </button>
                        <button type="button" class="absolute -bottom-1 -right-1 flex h-9 w-9 items-center justify-center rounded-full bg-emerald-600 text-white shadow ring-2 ring-white hover:bg-emerald-700 dark:ring-slate-800"
                            title="Ganti foto" aria-label="Ganti foto" @click="fileInput.click()">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.66-.9l.82-1.2A2 2 0 0110.07 4h3.86a2 2 0 011.66.9l.82 1.2a2 2 0 001.66.9H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </button>
                        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pickFile" />
                    </div>

                    <div class="min-w-0 flex-1 text-center sm:text-left">
                        <h2 class="truncate text-xl font-semibold text-slate-800 dark:text-slate-100">{{ profile.name }}</h2>
                        <p v-if="profile.job_title || profile.department" class="text-sm text-slate-600 dark:text-slate-300">
                            {{ [profile.job_title, profile.department].filter(Boolean).join(' · ') }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ profile.role ?? 'belum ada role' }}</span>
                            <span v-if="profile.position" class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 font-medium text-sky-700 dark:bg-sky-950 dark:text-sky-300">{{ profile.position }}</span>
                            <span v-if="profile.member_since" class="ml-2">Bergabung {{ profile.member_since }}</span>
                        </p>
                        <div class="mt-3 flex flex-wrap justify-center gap-2 sm:justify-start">
                            <button type="button" :disabled="uploading" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200 disabled:opacity-50 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600" @click="fileInput.click()">
                                {{ uploading ? 'Mengunggah…' : profile.avatar_url ? 'Ganti foto' : 'Unggah foto' }}
                            </button>
                            <button v-if="profile.avatar_url" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950" @click="removePhoto">Hapus foto</button>
                        </div>
                        <p v-if="photoError" role="alert" class="mt-2 text-xs text-rose-600">{{ photoError }}</p>
                        <p v-else class="mt-2 text-xs text-slate-400">JPG, PNG, atau WebP. Kamu bisa zoom & geser sebelum menyimpan.</p>
                    </div>
                </div>
            </div>

            <!-- Form utama -->
            <form :class="cardClass" class="space-y-8" @submit.prevent="save">
                <ErrorSummary :errors="form.errors" />

                <section>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Informasi Akun</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="p-name">Nama lengkap</label>
                            <input id="p-name" v-model="form.name" type="text" required autocomplete="name" :class="inputClass" />
                        </div>
                        <div>
                            <label :class="labelClass" for="p-username">Username</label>
                            <input id="p-username" v-model="form.username" type="text" autocomplete="username" placeholder="mis. budi.santoso" :class="inputClass" />
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass" for="p-email">Email</label>
                            <input id="p-email" v-model="form.email" type="email" required autocomplete="email" :class="inputClass" />
                            <p class="mt-1 text-xs text-slate-400">Email ini dipakai untuk login dan menerima kode verifikasi.</p>
                        </div>
                        <div v-if="emailChanged" class="sm:col-span-2 rounded-lg bg-amber-50 p-3 dark:bg-amber-950">
                            <label :class="labelClass" for="p-curpw">Password saat ini (wajib untuk mengganti email)</label>
                            <input id="p-curpw" v-model="form.current_password" type="password" autocomplete="current-password" :class="inputClass" />
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Data Pribadi & Pekerjaan</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :class="labelClass" for="p-phone">Nomor telepon</label>
                            <input id="p-phone" v-model="form.phone" type="tel" autocomplete="tel" placeholder="0812-3456-7890" :class="inputClass" />
                        </div>
                        <div>
                            <label :class="labelClass" for="p-birth">Tanggal lahir</label>
                            <input id="p-birth" v-model="form.birth_date" type="date" autocomplete="bday" :class="inputClass" />
                        </div>
                        <div>
                            <label :class="labelClass" for="p-nip">NIP / No. induk pegawai</label>
                            <input id="p-nip" v-model="form.employee_id" type="text" :class="inputClass" />
                        </div>
                        <div>
                            <label :class="labelClass" for="p-job">Jabatan</label>
                            <input id="p-job" v-model="form.job_title" type="text" placeholder="mis. Staf Akuntansi" :class="inputClass" />
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass" for="p-dept">Divisi / Departemen</label>
                            <input id="p-dept" v-model="form.department" type="text" placeholder="mis. Keuangan" :class="inputClass" />
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass" for="p-addr">Alamat</label>
                            <textarea id="p-addr" v-model="form.address" rows="2" :class="inputClass"></textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <label :class="labelClass" for="p-bio">Tentang saya</label>
                            <textarea id="p-bio" v-model="form.bio" rows="3" maxlength="1000" placeholder="Ceritakan singkat tentang dirimu (opsional)" :class="inputClass"></textarea>
                            <p class="mt-1 text-right text-xs text-slate-400">{{ form.bio.length }}/1000</p>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Riwayat Pendidikan</h3>
                        <button type="button" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300" @click="addEducation">+ Tambah</button>
                    </div>
                    <p v-if="!form.education.length" class="mt-3 rounded-lg bg-slate-50 px-4 py-6 text-center text-sm text-slate-400 dark:bg-slate-900/40">Belum ada riwayat pendidikan.</p>
                    <div v-for="(edu, i) in form.education" :key="i" class="mt-3 rounded-xl border border-slate-100 p-4 dark:border-slate-700">
                        <div class="grid gap-3 sm:grid-cols-6">
                            <div class="sm:col-span-2">
                                <label :class="labelClass">Jenjang</label>
                                <select v-model="edu.level" :class="inputClass">
                                    <option value="">—</option>
                                    <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <label :class="labelClass">Institusi</label>
                                <input v-model="edu.institution" type="text" placeholder="mis. Universitas Andalas" :class="inputClass" />
                            </div>
                            <div class="sm:col-span-3">
                                <label :class="labelClass">Jurusan / Bidang</label>
                                <input v-model="edu.major" type="text" :class="inputClass" />
                            </div>
                            <div class="sm:col-span-1">
                                <label :class="labelClass">Mulai</label>
                                <input v-model="edu.start_year" type="number" min="1950" max="2100" placeholder="2015" :class="inputClass" />
                            </div>
                            <div class="sm:col-span-1">
                                <label :class="labelClass">Selesai</label>
                                <input v-model="edu.end_year" type="number" min="1950" max="2100" placeholder="2019" :class="inputClass" />
                            </div>
                            <div class="flex items-end sm:col-span-1">
                                <button type="button" class="w-full rounded-lg px-2 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950" @click="removeEducation(i)">Hapus</button>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </form>

            <!-- Ganti password -->
            <form :class="cardClass" @submit.prevent="savePassword">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Ganti Password</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label :class="labelClass" for="pw-cur">Password saat ini</label>
                        <input id="pw-cur" v-model="pwForm.current_password" type="password" autocomplete="current-password" :class="inputClass" />
                        <p v-if="pwForm.errors.current_password" class="mt-1 text-xs text-rose-600">{{ pwForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="pw-new">Password baru</label>
                        <input id="pw-new" v-model="pwForm.password" type="password" autocomplete="new-password" :class="inputClass" />
                        <p v-if="pwForm.errors.password" class="mt-1 text-xs text-rose-600">{{ pwForm.errors.password }}</p>
                    </div>
                    <div>
                        <label :class="labelClass" for="pw-conf">Ulangi password baru</label>
                        <input id="pw-conf" v-model="pwForm.password_confirmation" type="password" autocomplete="new-password" :class="inputClass" />
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" :disabled="pwForm.processing" class="rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-600 dark:hover:bg-slate-500">
                        {{ pwForm.processing ? 'Menyimpan…' : 'Ganti Password' }}
                    </button>
                </div>
            </form>
        </div>

        <ImageCropper v-if="cropOpen && pickedFile" :file="pickedFile" shape="circle" format="jpeg" :output-size="512" title="Atur Foto Profil"
            @confirm="uploadCropped" @cancel="cropOpen = false" />
        <PhotoViewer v-if="viewerOpen && profile.avatar_url" :src="profile.avatar_url" :alt="profile.name" @close="viewerOpen = false" />
    </AppLayout>
</template>
