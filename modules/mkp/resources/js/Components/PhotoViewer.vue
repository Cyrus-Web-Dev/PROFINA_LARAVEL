<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

/**
 * Penampil foto layar penuh dengan ZOOM: roda mouse, tombol +/−, klik dua kali (2,5x),
 * seret untuk menggeser saat diperbesar, Esc untuk menutup.
 */
const props = defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: 'Foto' },
})
const emit = defineEmits(['close'])

const MIN = 1
const MAX = 6
const zoom = ref(1)
const pos = ref({ x: 0, y: 0 })
const dragging = ref(false)
let last = null

function setZoom(z) {
    zoom.value = Math.min(MAX, Math.max(MIN, z))
    if (zoom.value === MIN) pos.value = { x: 0, y: 0 }
}
function onWheel(e) { setZoom(zoom.value * (e.deltaY < 0 ? 1.15 : 1 / 1.15)) }
function onDown(e) {
    if (zoom.value <= 1) return
    dragging.value = true
    last = { x: e.clientX, y: e.clientY }
    e.currentTarget.setPointerCapture(e.pointerId)
}
function onMove(e) {
    if (!dragging.value) return
    pos.value = { x: pos.value.x + e.clientX - last.x, y: pos.value.y + e.clientY - last.y }
    last = { x: e.clientX, y: e.clientY }
}
function onUp() { dragging.value = false }
function toggle() { setZoom(zoom.value > 1 ? 1 : 2.5) }
function onKey(e) {
    if (e.key === 'Escape') emit('close')
    else if (e.key === '+' || e.key === '=') setZoom(zoom.value * 1.25)
    else if (e.key === '-') setZoom(zoom.value / 1.25)
    else if (e.key === '0') setZoom(1)
}

onMounted(() => {
    document.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'
})
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey)
    document.body.style.overflow = ''
})
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/85 p-4" role="dialog" aria-modal="true" :aria-label="alt"
        @click.self="emit('close')" @wheel.prevent="onWheel">
        <img :src="src" :alt="alt" draggable="false"
            class="max-h-[85vh] max-w-[90vw] select-none rounded-lg object-contain shadow-2xl"
            :class="zoom > 1 ? 'cursor-grab' : 'cursor-zoom-in'"
            :style="{ transform: `translate(${pos.x}px, ${pos.y}px) scale(${zoom})`, transition: dragging ? 'none' : 'transform 0.15s ease' }"
            @dblclick="toggle" @pointerdown="onDown" @pointermove="onMove" @pointerup="onUp" @pointercancel="onUp" />

        <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full bg-slate-900/90 px-2 py-1.5 text-white shadow-lg">
            <button type="button" class="h-8 w-8 rounded-full text-lg hover:bg-white/10" aria-label="Perkecil" @click="setZoom(zoom / 1.25)">−</button>
            <button type="button" class="min-w-[3.5rem] rounded-full px-2 text-xs hover:bg-white/10" title="Kembali ke ukuran awal" @click="setZoom(1)">{{ Math.round(zoom * 100) }}%</button>
            <button type="button" class="h-8 w-8 rounded-full text-lg hover:bg-white/10" aria-label="Perbesar" @click="setZoom(zoom * 1.25)">+</button>
        </div>
        <button type="button" class="absolute right-4 top-4 h-10 w-10 rounded-full bg-slate-900/90 text-2xl leading-none text-white hover:bg-slate-800" aria-label="Tutup" @click="emit('close')">&times;</button>
    </div>
</template>
