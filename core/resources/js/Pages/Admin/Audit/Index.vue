<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'
import { reactive } from 'vue'

const props = defineProps({ logs: Object, filters: Object })
const f = reactive({ q: props.filters.q ?? '', action: props.filters.action ?? '' })
const search = () => router.get(route('admin.audit'), { ...f }, { preserveState: true, replace: true })
</script>

<template>
  <AppLayout>
    <Head title="Audit" />
    <template #title>Audit Aktivitas</template>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="search">
      <input v-model="f.q" class="input max-w-xs" placeholder="Cari deskripsi…" />
      <input v-model="f.action" class="input max-w-[12rem]" placeholder="Awalan aksi (login, sso, user…)" />
      <button class="btn-secondary">Cari</button>
    </form>
    <div class="card overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Deskripsi</th><th>IP</th></tr></thead>
        <tbody>
          <tr v-for="l in logs.data" :key="l.id"><td class="font-mono">{{ l.at }}</td><td>{{ l.user || '-' }}</td><td><span class="badge-slate">{{ l.action }}</span></td><td>{{ l.description }}</td><td class="font-mono">{{ l.ip }}</td></tr>
          <tr v-if="!logs.data.length"><td colspan="5" class="py-6 text-center text-ink-500">Belum ada catatan.</td></tr>
        </tbody>
      </table>
    </div>
    <Pagination :links="logs.links" />
  </AppLayout>
</template>
