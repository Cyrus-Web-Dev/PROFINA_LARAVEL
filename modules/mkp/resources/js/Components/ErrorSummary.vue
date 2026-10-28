<script setup>
import { computed } from 'vue'

// Menampilkan SEMUA pesan error validasi form dalam satu kotak, termasuk error baris
// (mis. "lines.0.account_id") yang sebelumnya tidak tampil di mana pun.
const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    // Kunci ini sudah ditampilkan oleh banner di AppLayout, jadi tidak diulang di sini.
    exclude: { type: Array, default: () => ['status', 'approval', 'balance', 'delete', 'gmail', 'asset', 'period', 'general'] },
})

const messages = computed(() => {
    const out = []
    for (const [key, msg] of Object.entries(props.errors || {})) {
        if (props.exclude.includes(key) || !msg) continue
        if (!out.includes(msg)) out.push(msg)
    }
    return out
})
</script>

<template>
    <div v-if="messages.length" role="alert" class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
        <p class="font-medium">Periksa kembali isian kamu:</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
            <li v-for="m in messages" :key="m">{{ m }}</li>
        </ul>
    </div>
</template>
