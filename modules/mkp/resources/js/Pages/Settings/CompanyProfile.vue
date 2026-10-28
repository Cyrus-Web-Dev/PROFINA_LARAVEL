<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import ImageCropper from '@/Components/ImageCropper.vue'
import PhotoViewer from '@/Components/PhotoViewer.vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({ profile: Object })

const form = useForm({
  name: props.profile.name,
  legal_name: props.profile.legal_name,
  npwp: props.profile.npwp,
  address: props.profile.address,
  phone: props.profile.phone,
  email: props.profile.email,
})

// ---- Logo perusahaan (crop + zoom) ----
const fileInput = ref(null)
const pickedFile = ref(null)
const cropOpen = ref(false)
const viewerOpen = ref(false)
const logoError = ref('')
const uploading = ref(false)

function pickFile(e) {
  const f = e.target.files?.[0]
  e.target.value = ''
  if (!f) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { logoError.value = 'Format harus JPG, PNG, atau WebP.'; return }
  if (f.size > 20 * 1024 * 1024) { logoError.value = 'File terlalu besar (maksimal 20 MB).'; return }
  logoError.value = ''
  pickedFile.value = f
  cropOpen.value = true
}
function uploadCropped(file) {
  cropOpen.value = false
  uploading.value = true
  router.post(route('settings.company.logo.upload'), { logo: file }, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errs) => { logoError.value = Object.values(errs)[0] ?? 'Gagal mengunggah logo.' },
    onFinish: () => { uploading.value = false },
  })
}
function removeLogo() {
  if (confirm('Hapus logo perusahaan?')) router.delete(route('settings.company.logo.delete'), { preserveScroll: true })
}

function submit() {
  form.put(route('settings.company.update'))
}
</script>

<template>
  <AppLayout>
    <template #title>Profil Perusahaan</template>

    <div class="mx-auto max-w-xl rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Data ini dipakai sebagai kop surat pada PDF Invoice yang dikirim ke pelanggan.</p>
      <div class="mb-6 flex items-center gap-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-900/40">
        <button type="button" :disabled="!profile.logo_url" class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white ring-1 ring-slate-200 disabled:cursor-default dark:ring-slate-700"
          :title="profile.logo_url ? 'Klik untuk memperbesar' : ''" @click="viewerOpen = true">
          <img v-if="profile.logo_url" :src="profile.logo_url" alt="Logo perusahaan" class="max-h-full max-w-full object-contain" />
          <span v-else class="px-2 text-center text-xs text-slate-400">Belum ada logo</span>
        </button>
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Logo perusahaan</p>
          <p class="text-xs text-slate-400">Tampil di kop PDF Invoice. JPG, PNG, atau WebP; bisa di-zoom sebelum disimpan.</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" :disabled="uploading" class="rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-300 disabled:opacity-50 dark:bg-slate-700 dark:text-slate-200" @click="fileInput.click()">
              {{ uploading ? 'Mengunggah…' : profile.logo_url ? 'Ganti logo' : 'Unggah logo' }}
            </button>
            <button v-if="profile.logo_url" type="button" class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950" @click="removeLogo">Hapus</button>
          </div>
          <p v-if="logoError" role="alert" class="mt-1 text-xs text-rose-600">{{ logoError }}</p>
          <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pickFile" />
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nama Perusahaan (tampil di PDF)</label><input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nama Badan Hukum (opsional)</label><input v-model="form.legal_name" type="text" placeholder="PT Contoh Sejahtera Abadi" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">NPWP</label><input v-model="form.npwp" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Alamat</label><textarea v-model="form.address" rows="2" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm"></textarea></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Telepon</label><input v-model="form.phone" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email</label><input v-model="form.email" type="email" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
        </div>
        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-40">Simpan</button>
        </div>
      </form>
    </div>
    <ImageCropper v-if="cropOpen && pickedFile" :file="pickedFile" shape="square" format="png" :output-size="600" allow-contain title="Atur Logo Perusahaan"
      @confirm="uploadCropped" @cancel="cropOpen = false" />
    <PhotoViewer v-if="viewerOpen && profile.logo_url" :src="profile.logo_url" alt="Logo perusahaan" @close="viewerOpen = false" />
  </AppLayout>
</template>
