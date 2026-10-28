<script setup>
import { useCan } from '@/permissions'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ vendors: Array, filters: Object })
const showModal = ref(false)
const editingId = ref(null)
const search = ref(props.filters?.search ?? '')
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)

const form = useForm({ code: '', name: '', address: '', phone: '', email: '', npwp: '', is_active: true })

function openCreate() {
  editingId.value = null
  form.reset()
  form.is_active = true
  showModal.value = true
}
function openEdit(v) {
  editingId.value = v.id
  form.code = v.code
  form.name = v.name
  form.address = v.address
  form.phone = v.phone
  form.email = v.email
  form.npwp = v.npwp
  form.is_active = v.is_active
  showModal.value = true
}
function submit() {
  if (editingId.value) {
    form.put(route('vendors.update', editingId.value), { onSuccess: () => { showModal.value = false } })
  } else {
    form.post(route('vendors.store'), { onSuccess: () => { showModal.value = false; form.reset() } })
  }
}
function runSearch() {
  router.get(route('vendors.index'), { search: search.value }, { preserveState: true, replace: true })
}
function destroyVendor(v) {
  if (confirm(`Hapus vendor ${v.name}?`)) router.delete(route('vendors.destroy', v.id))
}
</script>

<template>
  <AppLayout>
    <template #title>Vendor</template>
    <template #actions>
      <button v-if="can('ap.manage')" @click="openCreate" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">+ Tambah Vendor</button>
    </template>

    <div class="rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-700 p-4">
        <input v-model="search" @keyup.enter="runSearch" type="text" placeholder="Cari vendor..." class="w-full max-w-sm rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
        <button @click="runSearch" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Cari</button>
      </div>
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3">Kode</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Kontak</th>
            <th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Sisa Utang</th><th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in vendors" :key="v.id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400">{{ v.code }}</td>
            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ v.name }}</td>
            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.phone || v.email || '-' }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="v.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'">{{ v.is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </td>
            <td class="px-4 py-3 text-right font-medium" :class="v.outstanding > 0 ? 'text-amber-600' : 'text-slate-400 dark:text-slate-500'">{{ formatIDR(v.outstanding) }}</td>
            <td class="px-4 py-3 text-right space-x-3">
              <button v-if="can('ap.manage')" @click="openEdit(v)" class="text-xs font-medium text-emerald-600 hover:underline">Edit</button>
              <button v-if="can('ap.manage')" @click="destroyVendor(v)" class="text-xs font-medium text-rose-500 hover:underline">Hapus</button>
            </td>
          </tr>
          <tr v-if="vendors.length === 0"><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada vendor.</td></tr>
        </tbody>
      </table>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ editingId ? 'Edit Vendor' : 'Tambah Vendor' }}</h3>
        <form @submit.prevent="submit" class="mt-4 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Kode</label>
              <input v-model="form.code" type="text" :disabled="!!editingId" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm disabled:bg-slate-50 disabled:text-slate-400" />
            </div>
            <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nama</label><input v-model="form.name" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          </div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Alamat</label><input v-model="form.address" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Telepon</label><input v-model="form.phone" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
            <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Email</label><input v-model="form.email" type="email" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          </div>
          <div v-if="editingId" class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded border-slate-300 text-emerald-600" />
            <label for="is_active" class="text-sm text-slate-600 dark:text-slate-300">Aktif</label>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
