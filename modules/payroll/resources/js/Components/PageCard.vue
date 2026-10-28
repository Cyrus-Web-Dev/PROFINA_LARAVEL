<script setup>
defineProps({ title: String, subtitle: String })
</script>

<template>
  <section class="card">
    <header v-if="title || $slots.actions" class="card-header flex items-center justify-between gap-3">
      <div>
        <h2 class="text-base font-semibold">{{ title }}</h2>
        <p v-if="subtitle" class="text-xs text-ink-500">{{ subtitle }}</p>
      </div>
      <div class="flex items-center gap-2"><slot name="actions" /></div>
    </header>
    <div class="card-body"><slot /></div>
  </section>
</template>
