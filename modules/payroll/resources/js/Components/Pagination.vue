<script setup>
import { Link } from '@inertiajs/vue3'

// Tautan halaman dari paginator Laravel (props.links = employees.links).
defineProps({
    links: { type: Array, default: () => [] },
})
</script>

<template>
    <nav v-if="links.length > 3" class="mt-4 flex flex-wrap items-center justify-center gap-1" aria-label="Halaman">
        <template v-for="(link, i) in links" :key="i">
            <span v-if="!link.url" class="rounded-md px-3 py-1.5 text-sm text-ink-400" v-html="link.label" />
            <Link v-else :href="link.url" preserve-scroll
                class="rounded-md px-3 py-1.5 text-sm"
                :class="link.active ? 'bg-seal-500 font-medium text-ink-950' : 'text-ink-600 hover:bg-ink-100 dark:text-ink-300 dark:hover:bg-ink-800'"
                v-html="link.label" />
        </template>
    </nav>
</template>
