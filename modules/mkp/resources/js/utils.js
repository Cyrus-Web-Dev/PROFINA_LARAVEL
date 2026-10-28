/** Tanggal HARI INI menurut jam lokal browser, format YYYY-MM-DD.
 *  (new Date().toISOString() memakai UTC, sehingga sebelum jam 07:00 WIB hasilnya masih "kemarin".) */
export function todayLocal() {
    const d = new Date()
    const p = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

export function formatIDR(value) {
    const n = Number(value) || 0
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n)
}

export function formatNumber(value, digits = 2) {
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: digits }).format(Number(value) || 0)
}

/**
 * Kecilkan foto besar (mis. foto nota dari HP 4-8 MB) sebelum diunggah: sisi terpanjang maks 2200 px, JPEG 85%.
 * File PDF / gambar yang sudah kecil dikembalikan apa adanya. Bila gagal, file asli dipakai.
 */
export async function compressImage(file, maxSide = 2200, threshold = 1.5 * 1024 * 1024) {
    if (!file.type.startsWith('image/') || file.size <= threshold) return file

    try {
        const url = URL.createObjectURL(file)
        const img = await new Promise((resolve, reject) => {
            const im = new Image()
            im.onload = () => resolve(im)
            im.onerror = reject
            im.src = url
        })
        URL.revokeObjectURL(url)

        const ratio = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight))
        const canvas = document.createElement('canvas')
        canvas.width = Math.round(img.naturalWidth * ratio)
        canvas.height = Math.round(img.naturalHeight * ratio)
        const ctx = canvas.getContext('2d')
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, canvas.width, canvas.height)
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height)

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85))
        if (!blob || blob.size >= file.size) return file

        return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' })
    } catch (e) {
        return file
    }
}
