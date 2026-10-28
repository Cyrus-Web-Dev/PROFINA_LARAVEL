<script setup>
import { computed } from 'vue'

const props = defineProps({ value: { type: [Number, String], default: 0 }, signed: Boolean, muted: Boolean })
const n = computed(() => Number(props.value ?? 0))
const text = computed(() => (props.signed && n.value > 0 ? '+' : '') + 'Rp ' + n.value.toLocaleString('id-ID', { maximumFractionDigits: 0 }).replace('-', '−'))
</script>

<template><span class="num whitespace-nowrap" :class="[muted ? 'text-ink-500' : '', n < 0 ? 'text-stamp-600' : '']">{{ text }}</span></template>
