<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

/**
 * Editor foto dengan ZOOM dan geser (tanpa library). Alur: pilih file -> atur posisi & zoom
 * di dalam bingkai -> "Simpan" menghasilkan File hasil potongan (event `confirm`).
 *   - Roda mouse / slider / tombol +− / cubit dua jari untuk zoom, seret untuk menggeser.
 *   - allowContain: izinkan zoom-out sampai seluruh gambar muat di bingkai (cocok untuk logo).
 */
const props = defineProps({
    file: { type: File, required: true },
    shape: { type: String, default: 'circle' },       // 'circle' | 'square'
    format: { type: String, default: 'jpeg' },        // 'jpeg' (latar putih) | 'png' (transparan)
    outputSize: { type: Number, default: 512 },
    allowContain: { type: Boolean, default: false },
    title: { type: String, default: 'Atur Foto' },
})
const emit = defineEmits(['confirm', 'cancel'])

const VIEW = 288      // sisi bingkai (px)
const MAX_ZOOM = 5

const img = ref(null)
const failed = ref(false)
const busy = ref(false)
const zoom = ref(1)                 // pengali dari skala "menutupi bingkai"
const pos = ref({ x: 0, y: 0 })     // pojok kiri-atas gambar relatif ke bingkai
let objectUrl = null

const coverScale = computed(() => (img.value ? VIEW / Math.min(img.value.naturalWidth, img.value.naturalHeight) : 1))
const minZoom = computed(() => {
    if (!img.value || !props.allowContain) return 1
    return Math.min(img.value.naturalWidth, img.value.naturalHeight) / Math.max(img.value.naturalWidth, img.value.naturalHeight)
})
const scale = computed(() => coverScale.value * zoom.value)
const dispW = computed(() => (img.value ? img.value.naturalWidth * scale.value : 0))
const dispH = computed(() => (img.value ? img.value.naturalHeight * scale.value : 0))

function clampAxis(p, disp) {
    if (disp <= VIEW) return (VIEW - disp) / 2   // lebih kecil dari bingkai: taruh di tengah
    return Math.min(0, Math.max(VIEW - disp, p))
}
function clamp() {
    pos.value = { x: clampAxis(pos.value.x, dispW.value), y: clampAxis(pos.value.y, dispH.value) }
}
function setZoom(next) {
    next = Math.min(MAX_ZOOM, Math.max(minZoom.value, next))
    // Pertahankan titik yang sedang berada di tengah bingkai
    const cx = (VIEW / 2 - pos.value.x) / scale.value
    const cy = (VIEW / 2 - pos.value.y) / scale.value
    zoom.value = next
    pos.value = { x: VIEW / 2 - cx * scale.value, y: VIEW / 2 - cy * scale.value }
    clamp()
}

// --- interaksi ---
const pointers = new Map()
let startDist = 0
let startZoom = 1

function dist() {
    const [a, b] = [...pointers.values()]
    return Math.hypot(a.x - b.x, a.y - b.y)
}
function onDown(e) {
    e.currentTarget.setPointerCapture(e.pointerId)
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
    if (pointers.size === 2) { startDist = dist(); startZoom = zoom.value }
}
function onMove(e) {
    const prev = pointers.get(e.pointerId)
    if (!prev) return
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
    if (pointers.size === 2) {
        if (startDist > 0) setZoom(startZoom * (dist() / startDist))
        return
    }
    pos.value = { x: pos.value.x + (e.clientX - prev.x), y: pos.value.y + (e.clientY - prev.y) }
    clamp()
}
function onUp(e) { pointers.delete(e.pointerId) }
function onWheel(e) { setZoom(zoom.value * (e.deltaY < 0 ? 1.1 : 1 / 1.1)) }
function onKey(e) { if (e.key === 'Escape') emit('cancel') }

onMounted(() => {
    document.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'

    objectUrl = URL.createObjectURL(props.file)
    const im = new Image()
    im.onload = () => {
        img.value = im
        zoom.value = 1
        pos.value = { x: (VIEW - im.naturalWidth * coverScale.value) / 2, y: (VIEW - im.naturalHeight * coverScale.value) / 2 }
        clamp()
    }
    im.onerror = () => { failed.value = true }
    im.src = objectUrl
})
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey)
    document.body.style.overflow = ''
    if (objectUrl) URL.revokeObjectURL(objectUrl)
})

function confirmCrop() {
    if (!img.value || busy.value) return
    busy.value = true

    const out = props.outputSize
    const canvas = document.createElement('canvas')
    canvas.width = out
    canvas.height = out
    const ctx = canvas.getContext('2d')
    if (props.format === 'jpeg') {
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, out, out)
    }
    ctx.imageSmoothingQuality = 'high'

    // Bagian gambar asli yang terlihat di bingkai -> seluruh kanvas keluaran
    const s = scale.value
    ctx.drawImage(img.value, -pos.value.x / s, -pos.value.y / s, VIEW / s, VIEW / s, 0, 0, out, out)

    const mime = props.format === 'png' ? 'image/png' : 'image/jpeg'
    canvas.toBlob((blob) => {
        busy.value = false
        if (!blob) { failed.value = true; return }
        emit('confirm', new File([blob], props.format === 'png' ? 'logo.png' : 'foto.jpg', { type: mime }))
    }, mime, 0.9)
}
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" :aria-label="title">
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-800">
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">{{ title }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Geser untuk memposisikan, gunakan slider atau roda mouse untuk zoom.</p>

            <p v-if="failed" class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                Gambar tidak bisa dibaca. Coba file JPG/PNG/WebP lain.
            </p>

            <template v-else>
                <div class="relative mx-auto mt-4 touch-none select-none overflow-hidden rounded-lg bg-slate-200 dark:bg-slate-900"
                    :style="{ width: VIEW + 'px', height: VIEW + 'px', cursor: 'grab' }"
                    @pointerdown="onDown" @pointermove="onMove" @pointerup="onUp" @pointercancel="onUp" @wheel.prevent="onWheel">
                    <img v-if="img" :src="img.src" alt="" draggable="false" class="pointer-events-none absolute max-w-none"
                        :style="{ width: dispW + 'px', height: dispH + 'px', left: pos.x + 'px', top: pos.y + 'px' }" />
                    <!-- bingkai: bagian di luar bentuk digelapkan -->
                    <div class="pointer-events-none absolute inset-0 ring-1 ring-white/60"
                        :class="shape === 'circle' ? 'rounded-full' : ''"
                        style="box-shadow: 0 0 0 999px rgba(15, 23, 42, 0.55)"></div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <button type="button" class="h-8 w-8 rounded-lg bg-slate-100 text-lg leading-none text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200" aria-label="Perkecil" @click="setZoom(zoom / 1.2)">−</button>
                    <input type="range" class="flex-1 accent-emerald-600" aria-label="Zoom" :min="minZoom" :max="MAX_ZOOM" step="0.01"
                        :value="zoom" @input="setZoom(Number($event.target.value))" />
                    <button type="button" class="h-8 w-8 rounded-lg bg-slate-100 text-lg leading-none text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200" aria-label="Perbesar" @click="setZoom(zoom * 1.2)">+</button>
                </div>
            </template>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700" @click="emit('cancel')">Batal</button>
                <button type="button" :disabled="!img || busy || failed" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50" @click="confirmCrop">
                    {{ busy ? 'Memproses…' : 'Simpan' }}
                </button>
            </div>
        </div>
    </div>
</template>
