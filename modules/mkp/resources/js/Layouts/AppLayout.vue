<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import UserAvatar from '@/Components/UserAvatar.vue'

const page = usePage()
const sidebarOpen = ref(false)
const isDark = ref(false)
const notifOpen = ref(false)
const notifCount = ref(0)
const notifItems = ref([])
const notifWrap = ref(null)
const dismissedError = ref(false)
let notifTimer = null

const user = computed(() => page.props.auth?.user ?? null)
const isAdmin = computed(() => (user.value?.roles ?? []).includes('admin'))

// Pesan error NON-field (mis. gagal posting, akun tidak bisa dihapus). Sebelumnya pesan-pesan ini
// dikirim server tetapi tidak ditampilkan di mana pun, sehingga tombol terasa "tidak berfungsi".
const GLOBAL_ERROR_KEYS = ['status', 'approval', 'balance', 'delete', 'gmail', 'asset', 'period', 'general']
const bannerMessages = computed(() => {
  const msgs = []
  const flashError = page.props.flash?.error
  if (flashError) msgs.push(flashError)
  for (const key of GLOBAL_ERROR_KEYS) {
    const m = page.props.errors?.[key]
    if (m && !msgs.includes(m)) msgs.push(m)
  }
  return msgs
})

async function fetchNotifications() {
  if (document.hidden) return
  try {
    const { data } = await axios.get(route('notifications.summary'))
    notifCount.value = data.count
    notifItems.value = data.items
  } catch (e) {
    // notifikasi bukan fitur kritikal - gagal diam-diam
  }
}

function closeNotifOnClickOutside(e) {
  if (notifWrap.value && !notifWrap.value.contains(e.target)) notifOpen.value = false
}
function closeOnEscape(e) {
  if (e.key === 'Escape') { notifOpen.value = false; sidebarOpen.value = false }
}

onMounted(() => {
  fetchNotifications()
  notifTimer = setInterval(fetchNotifications, 120000) // tiap 2 menit, berhenti saat tab tersembunyi
  document.addEventListener('click', closeNotifOnClickOutside)
  document.addEventListener('keydown', closeOnEscape)

  try { isDark.value = localStorage.getItem('mkp-theme') === 'dark' } catch (e) { /* abaikan */ }
  applyTheme()
})
onUnmounted(() => {
  clearInterval(notifTimer)
  document.removeEventListener('click', closeNotifOnClickOutside)
  document.removeEventListener('keydown', closeOnEscape)
})

function applyTheme() {
  document.documentElement.classList.toggle('dark', isDark.value)
}
function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('mkp-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  applyTheme()
}

const nav = [
  { name: 'Dashboard', href: route('dashboard'), icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Daftar Akun (COA)', href: route('coa.index'), icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
  { name: 'Jurnal Umum', href: route('journals.index'), icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { name: 'Buku Besar', href: route('ledger.index'), icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5C21.363 17.363 20.5 19 19 19H5c-1.5 0-2.363-1.637-1.586-2.414l5-5A2 2 0 009 10.172V5L8 4z' },
  { name: 'Piutang Usaha (AR)', href: route('invoices.index'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-9c-1.11 0-2.08.402-2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'Pelanggan', href: route('customers.index'), icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.13a4 4 0 100-8 4 4 0 000 8zm6 2a4 4 0 10-8 0' },
  { name: 'Utang Usaha (AP)', href: route('bills.index'), icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z' },
  { name: 'Vendor', href: route('vendors.index'), icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { name: 'Kas & Bank', href: route('cashbank.index'), icon: 'M2 8h20M2 8v10a2 2 0 002 2h16a2 2 0 002-2V8M2 8l2-4h16l2 4M8 14h.01M12 14h4' },
  { name: 'Dana Pengadaan', badge: 'procurement', href: route('procurement.index'), icon: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1' },
  { name: 'Dana Payroll', href: route('payroll.index'), icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { name: 'Aset Tetap', href: route('fixed-assets.index'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-14h6m-6 4h6m-6 4h6' },
  { name: 'Anggaran', href: route('budgets.index'), icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2' },
  { name: 'Neraca Saldo', href: route('reports.trial-balance'), icon: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2' },
  { name: 'Laba Rugi', href: route('reports.income-statement'), icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
  { name: 'Neraca (Posisi Keuangan)', href: route('reports.balance-sheet'), icon: 'M3 10h18M3 14h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z' },
  { name: 'Arus Kas', href: route('reports.cash-flow'), icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z' },
  { name: 'Rasio Keuangan', href: route('reports.ratios'), icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z' },
  { name: 'Rekonsiliasi Bank', href: route('bank-reconciliation.index'), icon: 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z' },
  { name: 'Kelola Pengguna', admin: true, badge: 'pending', href: route('admin.users.index'), icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { name: 'Periode Akuntansi', admin: true, href: route('admin.periods.index'), icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' },
  { name: 'Backup Data', admin: true, href: route('admin.backup.index'), icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' },
  { name: 'Profil Perusahaan', admin: true, href: route('settings.company.edit'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-14h6m-6 4h6m-6 4h6' },
  { name: 'Activity Log', admin: true, href: route('settings.activity-log.index'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
]

const visibleNav = computed(() => nav.filter((item) => !item.admin || isAdmin.value))

function isActive(href) {
  const path = new URL(href).pathname
  const url = page.url.split('?')[0]
  return url === path || url.startsWith(path + '/')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <!-- Sidebar desktop -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-slate-900 lg:flex">
      <div class="flex h-16 shrink-0 items-center gap-2 px-6">
        <img src="/images/logo.svg" alt="Logo MKP" class="h-9 w-9 rounded-lg" />
        <div class="text-white">
          <p class="text-sm font-semibold leading-tight">MKP</p>
          <p class="text-xs text-slate-400 leading-tight">Manajemen Keuangan</p>
        </div>
      </div>
      <nav class="mt-2 flex-1 space-y-1 overflow-y-auto px-3 pb-4">
        <Link
          v-for="item in visibleNav"
          :key="item.name"
          :href="item.href"
          class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
          :class="isActive(item.href) ? 'bg-emerald-500/10 text-emerald-400' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
        >
          <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
          {{ item.name }}
          <span v-if="item.badge === 'pending' && $page.props.pending_users > 0" class="ml-auto rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white" title="Pendaftar menunggu persetujuan">{{ $page.props.pending_users }}</span>
          <span v-if="item.badge === 'procurement' && $page.props.procurement_badge > 0" class="ml-auto rounded-full bg-orange-500 px-2 py-0.5 text-[10px] font-bold text-white" title="Pengajuan menunggu keputusan + laporan baru">{{ $page.props.procurement_badge }}</span>
        </Link>
      </nav>
      <div class="shrink-0 border-t border-slate-800 p-4">
        <div class="flex items-center gap-3">
          <Link :href="route('profile.edit')" class="-m-1 flex min-w-0 flex-1 items-center gap-3 rounded-lg p-1 hover:bg-slate-800/70" title="Buka profil saya">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="36" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
              <p class="truncate text-xs text-slate-400" :title="user?.role_label">{{ [user?.job_title || user?.position, user?.role_label].filter(Boolean).join(' · ') }}</p>
            </div>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="shrink-0 text-slate-400 hover:text-white" title="Keluar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Sidebar mobile -->
    <div v-if="sidebarOpen" class="fixed inset-0 z-40 lg:hidden">
      <div class="absolute inset-0 bg-black/50" @click="sidebarOpen = false"></div>
      <aside class="absolute inset-y-0 left-0 flex w-64 flex-col bg-slate-900 p-4">
        <nav class="flex-1 space-y-1 overflow-y-auto">
          <Link v-for="item in visibleNav" :key="item.name" :href="item.href" class="block rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-800 hover:text-white" :class="isActive(item.href) ? 'bg-emerald-500/10 text-emerald-400' : 'text-slate-300'">
            {{ item.name }}
          <span v-if="item.badge === 'pending' && $page.props.pending_users > 0" class="ml-auto rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white" title="Pendaftar menunggu persetujuan">{{ $page.props.pending_users }}</span>
          </Link>
        </nav>
        <div class="shrink-0 border-t border-slate-800 pt-4">
          <Link :href="route('profile.edit')" class="mb-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="24" />
            Profil Saya
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Keluar
          </Link>
        </div>
      </aside>
    </div>

    <!-- Content -->
    <div class="lg:pl-64">
      <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/80 px-4 backdrop-blur lg:px-8 dark:border-slate-800 dark:bg-slate-900/80">
        <div class="flex items-center gap-3">
          <button class="text-slate-500 lg:hidden" @click="sidebarOpen = true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100"><slot name="title">Dashboard</slot></h1>
        </div>
        <div class="flex items-center gap-3">
          <button @click="toggleTheme" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" title="Ganti tema">
            <svg v-if="!isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </button>
          <div ref="notifWrap" class="relative">
            <button @click="notifOpen = !notifOpen" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800" title="Notifikasi">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span v-if="notifCount > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white">{{ notifCount > 9 ? '9+' : notifCount }}</span>
            </button>
            <div v-if="notifOpen" class="absolute right-0 z-30 mt-2 w-80 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-100 dark:bg-slate-800 dark:ring-slate-700">
              <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Notifikasi</p>
              <Link v-for="(item, i) in notifItems" :key="i" :href="item.url" @click="notifOpen = false"
                class="block rounded-lg px-3 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700"
                :class="{ 'text-rose-600': item.type === 'danger', 'text-amber-600': item.type === 'warning', 'text-slate-600 dark:text-slate-300': item.type === 'info' }">
                {{ item.text }}
              </Link>
              <p v-if="notifItems.length === 0" class="px-3 py-4 text-center text-sm text-slate-400">Tidak ada notifikasi baru.</p>
            </div>
          </div>
          <slot name="actions" />
        </div>
      </header>

      <div v-if="$page.props.flash?.success" role="status" class="mx-4 mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 lg:mx-8 dark:bg-emerald-950 dark:text-emerald-300">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="bannerMessages.length && !dismissedError" role="alert" class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 lg:mx-8 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300">
        <ul class="space-y-1">
          <li v-for="m in bannerMessages" :key="m">{{ m }}</li>
        </ul>
        <button type="button" class="shrink-0 font-bold opacity-60 hover:opacity-100" title="Tutup" @click="dismissedError = true">&times;</button>
      </div>

      <main class="p-4 lg:p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
