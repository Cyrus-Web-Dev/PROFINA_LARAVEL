<script setup>
/**
 * "Rel kontrol": tanda tangan berantai Dibuat -> Diverifikasi -> Disetujui -> Dicairkan -> Dibukukan.
 * Elemen khas PAYBRIDGE: tiap kotak memperlihatkan SIAPA & KAPAN, sehingga pemisahan tugas terlihat jelas.
 */
defineProps({ steps: { type: Array, required: true } }) // [{label, state: 'done'|'current'|'todo'|'rejected', who, at}]
</script>

<template>
  <ol class="grid grid-cols-2 gap-2 sm:grid-cols-5">
    <li v-for="(s, i) in steps" :key="i" class="relative rounded-md border px-3 py-2"
      :class="{
        'border-seal-300 bg-seal-50 dark:border-seal-700 dark:bg-seal-900/30': s.state === 'done',
        'border-ink-900 bg-white ring-1 ring-ink-900 dark:border-seal-400 dark:bg-ink-900 dark:ring-seal-400': s.state === 'current',
        'border-ink-200 bg-ink-50/60 dark:border-ink-800 dark:bg-ink-900/40': s.state === 'todo',
        'border-stamp-300 bg-stamp-50 dark:border-stamp-800 dark:bg-stamp-900/30': s.state === 'rejected',
      }">
      <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider"
        :class="s.state === 'done' ? 'text-seal-700 dark:text-seal-300' : s.state === 'rejected' ? 'text-stamp-700' : 'text-ink-500'">
        <span v-if="s.state === 'done'">✓</span><span v-else-if="s.state === 'rejected'">✕</span><span v-else class="font-mono">{{ i + 1 }}</span>
        {{ s.label }}
      </p>
      <p class="mt-1 truncate text-xs font-medium text-ink-800 dark:text-ink-200">{{ s.who || (s.state === 'current' ? 'Menunggu…' : '—') }}</p>
      <p class="num text-[10px] text-ink-500">{{ s.at || '' }}</p>
    </li>
  </ol>
</template>
