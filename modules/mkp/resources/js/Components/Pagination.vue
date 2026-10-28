<script setup>
import { Link } from '@inertiajs/vue3'

// Tautan halaman dari paginator Laravel (props.links = journals.links).
defineProps({
    links: { type: Array, default: () => [] },
})
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex flex-wrap items-center justify-center gap-1" aria-label="Halaman">
        <template v-for="(link, i) in links" :key="i">
            <span v-if="!link.url" class="rounded-lg px-3 py-1.5 text-sm text-slate-400" v-html="link.label" />
            <Link v-else :href="link.url" preserve-scroll
                class="rounded-lg px-3 py-1.5 text-sm"
                :class="link.active ? 'bg-emerald-600 font-medium text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                v-html="link.label" />
        </template>
    </nav>
</template>
