<script setup>
import { useCan } from '@/permissions'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AttachmentPanel from '@/Components/AttachmentPanel.vue'
import { router, Link } from '@inertiajs/vue3'
const can = useCan()

const props = defineProps({
  attachments: { type: Array, default: () => [] }, journal: Object, canApprove: Boolean })
const formatIDR = (v) => new Intl.NumberFormat('id-ID').format(v || 0)
const statusBadge = { draft: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300', posted: 'bg-emerald-100 text-emerald-700', void: 'bg-rose-100 text-rose-600' }
const approvalBadge = { not_required: null, pending: 'bg-amber-100 text-amber-700', approved: 'bg-emerald-100 text-emerald-700', rejected: 'bg-rose-100 text-rose-600' }
const approvalLabel = { pending: 'Menunggu Approval', approved: 'Disetujui', rejected: 'Ditolak' }
const showRejectModal = ref(false)
const rejectReason = ref('')

function postJournal() {
  if (confirm('Posting jurnal ini ke buku besar? Setelah diposting, jurnal tidak dapat diubah.')) {
    router.post(route('journals.post', props.journal.id))
  }
}
function voidJournal() {
  if (confirm('Batalkan (void) jurnal ini?')) {
    router.post(route('journals.void', props.journal.id))
  }
}
function deleteJournal() {
  if (confirm('Hapus jurnal draft ini? Tindakan ini tidak bisa dibatalkan.')) {
    router.delete(route('journals.destroy', props.journal.id))
  }
}
function approveJournal() {
  if (confirm('Setujui jurnal ini? Setelah disetujui, jurnal dapat diposting.')) {
    router.post(route('journals.approve', props.journal.id))
  }
}
function submitReject() {
  router.post(route('journals.reject', props.journal.id), { rejection_reason: rejectReason.value }, { onSuccess: () => { showRejectModal.value = false } })
}
</script>

<template>
  <AppLayout>
    <template #title>Detail Jurnal - {{ journal.journal_number }}</template>
    <template #actions>
      <button v-if="canApprove" @click="approveJournal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Setujui</button>
      <button v-if="canApprove" @click="showRejectModal = true" class="rounded-lg bg-rose-50 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-100">Tolak</button>
      <Link v-if="can('journal.create') && (journal.status === 'draft')" :href="route('journals.edit', journal.id)" class="rounded-lg bg-slate-100 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">Edit</Link>
      <button v-if="can('journal.create') && (journal.status === 'draft')" @click="deleteJournal" class="rounded-lg bg-rose-50 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-100">Hapus Draft</button>
      <button v-if="can('journal.post') && (journal.status === 'draft' && journal.approval_status !== 'pending' && journal.approval_status !== 'rejected')" @click="postJournal" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Posting Jurnal</button>
      <button v-if="can('journal.void') && (journal.status === 'posted')" @click="voidJournal" class="rounded-lg bg-rose-50 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-100">Void Jurnal</button>
    </template>

    <div v-if="journal.approval_status === 'pending'" class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">
      ⚠ Jurnal ini melebihi batas nilai dan menunggu approval sebelum dapat diposting ke buku besar.
    </div>
    <div v-if="journal.approval_status === 'rejected'" class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm font-medium text-rose-600">
      ✗ Jurnal ini ditolak{{ journal.rejection_reason ? `: ${journal.rejection_reason}` : '' }}.
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-sm ring-1 ring-slate-100 dark:ring-slate-700">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs text-slate-400 dark:text-slate-500">No. Jurnal</p>
          <p class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ journal.journal_number }}</p>
        </div>
        <div class="flex gap-2">
          <span v-if="approvalBadge[journal.approval_status]" class="rounded-full px-3 py-1 text-xs font-medium" :class="approvalBadge[journal.approval_status]">{{ approvalLabel[journal.approval_status] }}</span>
          <span class="rounded-full px-3 py-1 text-xs font-medium" :class="statusBadge[journal.status]">{{ journal.status }}</span>
        </div>
      </div>
      <div class="mt-4 grid grid-cols-3 gap-4 text-sm">
        <div><p class="text-xs text-slate-400 dark:text-slate-500">Tanggal</p><p class="text-slate-700 dark:text-slate-200">{{ journal.journal_date }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-slate-500">Referensi</p><p class="text-slate-700 dark:text-slate-200">{{ journal.reference || '-' }}</p></div>
        <div><p class="text-xs text-slate-400 dark:text-slate-500">Dibuat oleh</p><p class="text-slate-700 dark:text-slate-200">{{ journal.creator?.name || '-' }}</p></div>
      </div>
      <p v-if="journal.approver" class="mt-2 text-xs text-slate-400 dark:text-slate-500">Diproses oleh {{ journal.approver.name }} pada {{ journal.approved_at }}</p>
      <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">{{ journal.description }}</p>

      <div class="overflow-x-auto">
      <table class="mt-6 w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
            <th class="py-2">Akun</th><th class="py-2">Keterangan</th><th class="py-2 text-right">Debit</th><th class="py-2 text-right">Kredit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in journal.details" :key="d.id" class="border-b border-slate-50 dark:border-slate-800">
            <td class="py-2">{{ d.account.code }} - {{ d.account.name }}</td>
            <td class="py-2 text-slate-500 dark:text-slate-400">{{ d.description || '-' }}</td>
            <td class="py-2 text-right">{{ d.debit > 0 ? formatIDR(d.debit) : '-' }}</td>
            <td class="py-2 text-right">{{ d.credit > 0 ? formatIDR(d.credit) : '-' }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="border-t-2 border-slate-100 dark:border-slate-700 font-semibold">
            <td colspan="2" class="py-3 text-right text-slate-500 dark:text-slate-400">Total</td>
            <td class="py-3 text-right">{{ formatIDR(journal.total_debit) }}</td>
            <td class="py-3 text-right">{{ formatIDR(journal.total_credit) }}</td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>

    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-slate-800 p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Tolak Jurnal</h3>
        <form @submit.prevent="submitReject" class="mt-4 space-y-4">
          <div><label class="text-xs font-medium text-slate-500 dark:text-slate-400">Alasan (opsional)</label><textarea v-model="rejectReason" rows="3" class="mt-1 w-full rounded-lg border-slate-200 dark:border-slate-700 text-sm"></textarea></div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="showRejectModal = false" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">Tolak Jurnal</button>
          </div>
        </form>
      </div>
    </div>
    <AttachmentPanel type="journal" :record-id="journal.id" :attachments="attachments" :can-upload="can('journal.create')" />
  </AppLayout>
</template>
