<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import PhotoViewer from '@/Components/PhotoViewer.vue'
import { compressImage } from '@/utils'

// Panel lampiran bukti transaksi (foto nota / PDF) untuk jurnal, invoice, dan bill.
const props = defineProps({
    type: { type: String, required: true },          // 'journal' | 'invoice' | 'bill'
    recordId: { type: Number, required: true },
    attachments: { type: Array, default: () => [] },
    canUpload: { type: Boolean, default: false },
})

const page = usePage()
const fileInput = ref(null)
const dragging = ref(false)
const uploading = ref(false)
const error = ref('')
const viewing = ref(null)

const me = computed(() => page.props.auth?.user)
const isAdmin = computed(() => (me.value?.roles ?? []).includes('admin'))
const canDelete = (a) => isAdmin.value || a.uploaded_by === me.value?.id

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']

function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

async function upload(fileList) {
    error.value = ''
    const files = [...fileList]
    for (const original of files) {
        if (!ALLOWED.includes(original.type)) {
            error.value = `"${original.name}": format harus JPG, PNG, WebP, atau PDF.`
            continue
        }
        const file = await compressImage(original)
        if (file.size > 5 * 1024 * 1024) {
            error.value = `"${original.name}": ukuran melebihi 5 MB.`
            continue
        }
        uploading.value = true
        await new Promise((resolve) => {
            router.post(route('attachments.store'), { type: props.type, id: props.recordId, file }, {
                forceFormData: true,
                preserveScroll: true,
                onError: (errs) => { error.value = Object.values(errs)[0] ?? 'Gagal mengunggah.' },
                onFinish: resolve,
            })
        })
        uploading.value = false
    }
}
function onPick(e) {
    const list = e.target.files
    if (list?.length) upload(list)
    e.target.value = ''
}
function onDrop(e) {
    dragging.value = false
    if (props.canUpload && e.dataTransfer?.files?.length) upload(e.dataTransfer.files)
}
function open(a) {
    if (a.is_image) viewing.value = a
    else window.open(a.url, '_blank', 'noopener')
}
function remove(a) {
    if (confirm(`Hapus lampiran "${a.name}"?`)) router.delete(route('attachments.destroy', a.id), { preserveScroll: true })
}
</script>

<template>
    <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Lampiran bukti <span class="font-normal text-slate-400">({{ attachments.length }})</span></h3>
            <button v-if="canUpload" type="button" :disabled="uploading" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200 disabled:opacity-50 dark:bg-slate-700 dark:text-slate-200" @click="fileInput.click()">
                {{ uploading ? 'Mengunggah…' : '+ Tambah lampiran' }}
            </button>
        </div>

        <input ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="onPick" />

        <div v-if="canUpload" class="mt-3 rounded-xl border-2 border-dashed px-4 py-5 text-center text-xs transition"
            :class="dragging ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950' : 'border-slate-200 text-slate-400 dark:border-slate-700'"
            @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
            Tarik file ke sini atau klik "Tambah lampiran" — JPG, PNG, WebP, atau PDF, maks. 5 MB (foto besar dikecilkan otomatis).
        </div>

        <p v-if="error" role="alert" class="mt-2 text-xs text-rose-600">{{ error }}</p>

        <ul v-if="attachments.length" class="mt-4 grid gap-3 sm:grid-cols-2">
            <li v-for="a in attachments" :key="a.id" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 dark:border-slate-700">
                <button type="button" class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-700" :title="a.is_image ? 'Klik untuk memperbesar' : 'Buka PDF'" @click="open(a)">
                    <img v-if="a.is_image" :src="a.url" :alt="a.name" loading="lazy" class="h-full w-full object-cover" />
                    <span v-else class="text-xs font-bold text-rose-600">PDF</span>
                </button>
                <div class="min-w-0 flex-1">
                    <button type="button" class="block max-w-full truncate text-left text-sm font-medium text-slate-700 hover:underline dark:text-slate-200" @click="open(a)">{{ a.name }}</button>
                    <p class="text-xs text-slate-400">{{ formatSize(a.size) }} · {{ a.uploader ?? '—' }} · {{ a.created_at }}</p>
                </div>
                <button v-if="canDelete(a)" type="button" class="shrink-0 text-xs font-medium text-rose-600 hover:underline" @click="remove(a)">Hapus</button>
            </li>
        </ul>
        <p v-else class="mt-4 text-center text-sm text-slate-400">Belum ada lampiran.</p>

        <PhotoViewer v-if="viewing" :src="viewing.url" :alt="viewing.name" @close="viewing = null" />
    </section>
</template>
