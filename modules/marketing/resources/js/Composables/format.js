export const rp = (v) => 'Rp ' + Number(v ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
export const rp0 = (v) => 'Rp ' + Math.round(Number(v ?? 0)).toLocaleString('id-ID')
export const num = (v) => Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 3 })

export const STATUS = {
  draft: ['Draft', 'badge-slate'], sent: ['Terkirim', 'badge-amber'], approved: ['Disetujui', 'badge-green'], rejected: ['Ditolak', 'badge-red'], expired: ['Kedaluwarsa', 'badge-slate'],
  confirmed: ['Dikonfirmasi', 'badge-seal'], in_production: ['Produksi', 'badge-amber'], ready: ['Siap kirim', 'badge-green'], partial: ['Kirim sebagian', 'badge-amber'], delivered: ['Terkirim penuh', 'badge-green'], cancelled: ['Dibatalkan', 'badge-red'],
  shipped: ['Dalam perjalanan', 'badge-amber'], received: ['Diterima', 'badge-green'],
  open: ['Terbuka', 'badge-red'], in_progress: ['Diproses', 'badge-amber'], resolved: ['Selesai', 'badge-green'], closed: ['Ditutup', 'badge-slate'],
  prospect: ['Prospek', 'badge-amber'], active: ['Aktif', 'badge-green'], inactive: ['Nonaktif', 'badge-slate'],
}
export const statusLabel = (s) => (STATUS[s] ?? [s, 'badge-slate'])[0]
export const statusClass = (s) => (STATUS[s] ?? [s, 'badge-slate'])[1]
