export const rp = (v) => 'Rp ' + Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 0 }).replace('-', '−')
export const num = (v, d = 0) => Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: d })
export const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
