<script setup>
import { useCan } from '@/permissions'
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ErrorSummary from '@/Components/ErrorSummary.vue'
import { useForm, router } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({ accounts: Array, filters: Object })

const showModal = ref(false)
const editingAccount = ref(null)
const search = ref(props.filters?.search ?? '')

const typeLabel = { aset: 'Aset', kewajiban: 'Kewajiban', ekuitas: 'Ekuitas', pendapatan: 'Pendapatan', beban: 'Beban' }
const typeColor = { aset: 'bg-blue-100 text-blue-700', kewajiban: 'bg-amber-100 text-amber-700', ekuitas: 'bg-violet-100 text-violet-700', pendapatan: 'bg-emerald-100 text-emerald-700', beban: 'bg-rose-100 text-rose-700' }

const createForm = useForm({
  code: '', name: '', type: 'aset', category: '', normal_balance: 'debit', parent_id: null, is_header: false, is_cash_bank: false, description: '',
})
// Kategori menentukan pengelompokan di Arus Kas, Rasio Keuangan, dan daftar akun di form Invoice/Bill.
const categoryOptions = {
  aset: [['aset_lancar', 'Aset Lancar'], ['aset_tetap', 'Aset Tetap']],
  kewajiban: [['kewajiban_lancar', 'Kewajiban Jangka Pendek'], ['kewajiban_panjang', 'Kewajiban Jangka Panjang']],
}
const createCategories = computed(() => categoryOptions[createForm.type] ?? [])
// Saldo normal otomatis mengikuti tipe akun (bisa diubah manual untuk akun kontra)
watch(() => createForm.type, (type) => {
  createForm.normal_balance = ['aset', 'beban'].includes(type) ? 'debit' : 'kredit'
  createForm.category = ''
})

const editForm = useForm({ name: '', category: '', is_active: true, is_cash_bank: false, description: '' })

function openCreate() {
  editingAccount.value = null
  createForm.reset()
  showModal.value = true
}
function openEdit(acc) {
  editingAccount.value = acc
  editForm.name = acc.name
  editForm.category = acc.category
  editForm.is_active = acc.is_active
  editForm.is_cash_bank = acc.is_cash_bank
  editForm.description = acc.description
  showModal.value = true
}
function submitCreate() {
  createForm.post(route('coa.store'), { onSuccess: () => { showModal.value = false; createForm.reset() } })
}
function submitEdit() {
  editForm.put(route('coa.update', editingAccount.value.id), { onSuccess: () => { showModal.value = false } })
}
function runSearch() {
  router.get(route('coa.index'), { search: search.value }, { preserveState: true, replace: true })
}
function destroyAccount(account) {
  if (confirm(`Hapus akun ${account.code} - ${account.name}?`)) {
    router.delete(route('coa.destroy', account.id))
  }
}
</script>

<template>
  <AppLayout>
    <template #title>Daftar Akun (Chart of Accounts)</template>
    <template #actions>
      <button v-if="can('coa.manage')" @click="openCreate" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700">
        + Tambah Akun
      </button>
    </template>

    <div class="rounded-2xl bg-white dark:bg-slate-800 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-700 p-4">
        <input v-model="search" @keyup.enter="runSearch" type="text" placeholder="Cari kode atau nama akun..."
          class="w-full max-w-sm rounded-lg border-slate-200 dark:border-slate-700 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
        <button @click="runSearch" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Cari</button>
      </div>

      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="px-4 py-3">Kode</th>
            <th class="px-4 py-3">Nama Akun</th>
            <th class="px-4 py-3">Tipe</th>
            <th class="px-4 py-3">Saldo Normal</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="acc in accounts" :key="acc.id" class="border-b border-slate-50 hover:bg-slate-50/60 dark:hover:bg-slate-800/60">
            <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-300">{{ acc.code }}</td>
            <td class="px-4 py-3 font-medium" :class="acc.is_header ? 'text-slate-500 dark:text-slate-400' : 'text-slate-800 dark:text-slate-100'">
              <span v-if="acc.is_header" class="mr-1 text-xs text-slate-400 dark:text-slate-500">[Header]</span>{{ acc.name }}
              <span v-if="acc.is_cash_bank" class="ml-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">Kas/Bank</span>
            </td>
            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="typeColor[acc.type]">{{ typeLabel[acc.type] }}</span></td>
            <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ acc.normal_balance }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="acc.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'">
                {{ acc.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-3">
              <button v-if="can('coa.manage')" @click="openEdit(acc)" class="text-xs font-medium text-emerald-600 hover:underline">Edit</button>
              <button v-if="can('coa.manage')" @click="destroyAccount(acc)" class="text-xs font-medium text-rose-500 hover:underline">Hapus</button>
            </td>
          </tr>
          <tr v-if="accounts.length === 0"><td colspan="6" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">Belum ada akun.</td></tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- Modal Tambah Akun -->
    <div v-if="showModal && !editingAccount" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Tambah Akun Baru</h3>
        <form @submit.prevent="submitCreate" class="mt-4 space-y-4">
          <ErrorSummary :errors="createForm.errors" />
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Kode Akun</label>
              <input v-model="createForm.code" type="text" placeholder="1-1000" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
              <p v-if="createForm.errors.code" class="mt-1 text-xs text-rose-500">{{ createForm.errors.code }}</p>
            </div>
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Tipe Akun</label>
              <select v-model="createForm.type" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
                <option value="aset">Aset</option>
                <option value="kewajiban">Kewajiban</option>
                <option value="ekuitas">Ekuitas</option>
                <option value="pendapatan">Pendapatan</option>
                <option value="beban">Beban</option>
              </select>
            </div>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nama Akun</label>
            <input v-model="createForm.name" type="text" placeholder="Kas dan Setara Kas" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
            <p v-if="createForm.errors.name" class="mt-1 text-xs text-rose-500">{{ createForm.errors.name }}</p>
          </div>
          <div v-if="createCategories.length">
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Kategori</label>
            <select v-model="createForm.category" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
              <option value="">— pilih —</option>
              <option v-for="[value, label] in createCategories" :key="value" :value="value">{{ label }}</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">Wajib diisi agar akun ikut dihitung di Arus Kas & Rasio Keuangan.</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Saldo Normal</label>
              <select v-model="createForm.normal_balance" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm">
                <option value="debit">Debit</option>
                <option value="kredit">Kredit</option>
              </select>
            </div>
            <div class="flex items-center gap-2 pt-6">
              <input v-model="createForm.is_header" type="checkbox" id="is_header" class="rounded border-slate-300 text-emerald-600" />
              <label for="is_header" class="text-sm text-slate-600 dark:text-slate-300">Akun header (kelompok)</label>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="createForm.is_cash_bank" type="checkbox" id="is_cash_bank" class="rounded border-slate-300 text-emerald-600" />
              <label for="is_cash_bank" class="text-sm text-slate-600 dark:text-slate-300">Akun Kas/Bank</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" :disabled="createForm.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Edit Akun -->
    <div v-if="showModal && editingAccount" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Edit Akun {{ editingAccount.code }}</h3>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Kode, tipe, dan saldo normal tidak bisa diubah setelah akun dibuat — untuk menjaga konsistensi transaksi yang sudah tercatat.</p>
        <form @submit.prevent="submitEdit" class="mt-4 space-y-4">
          <div>
            <label class="text-xs font-medium text-slate-500 dark:text-slate-400">Nama Akun</label>
            <input v-model="editForm.name" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" />
            <p v-if="editForm.errors.name" class="mt-1 text-xs text-rose-500">{{ editForm.errors.name }}</p>
          </div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Kategori</label><input v-model="editForm.category" type="text" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm" /></div>
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Deskripsi</label><textarea v-model="editForm.description" rows="2" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm"></textarea></div>
          <div class="flex items-center gap-6">
            <div class="flex items-center gap-2">
              <input v-model="editForm.is_active" type="checkbox" id="edit_is_active" class="rounded border-slate-300 text-emerald-600" />
              <label for="edit_is_active" class="text-sm text-slate-600 dark:text-slate-300">Aktif</label>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="editForm.is_cash_bank" type="checkbox" id="edit_is_cash_bank" class="rounded border-slate-300 text-emerald-600" />
              <label for="edit_is_cash_bank" class="text-sm text-slate-600 dark:text-slate-300">Akun Kas/Bank</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Simpan Perubahan</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
