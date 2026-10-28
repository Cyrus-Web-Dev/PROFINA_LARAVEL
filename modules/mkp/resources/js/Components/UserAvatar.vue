<script setup>
import { ref, computed, watch } from 'vue'

// Foto profil bulat; bila belum ada foto (atau gagal dimuat) tampil inisial nama.
const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '?' },
    size: { type: Number, default: 36 }, // px
})

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

const initial = computed(() => (props.name || '?').trim().charAt(0).toUpperCase() || '?')
</script>

<template>
    <span class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-700 font-semibold text-white"
        :style="{ width: size + 'px', height: size + 'px', fontSize: Math.round(size * 0.4) + 'px' }">
        <img v-if="src && !failed" :src="src" :alt="name" class="h-full w-full object-cover" draggable="false" @error="failed = true" />
        <span v-else aria-hidden="true">{{ initial }}</span>
    </span>
</template>
