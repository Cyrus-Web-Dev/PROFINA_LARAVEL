<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserAvatar from '@/Components/UserAvatar.vue'

const page = usePage()
const sidebarOpen = ref(false)
const isDark = ref(false)
const dismissedError = ref(false)

const user = computed(() => page.props.auth?.user ?? null)
const perms = computed(() => user.value?.permissions ?? [])
const has = (p) => perms.value.includes(p)
const isStaff = computed(() => true)

const bannerMessages = computed(() => {
  const msgs = []
  if (page.props.flash?.error) msgs.push(page.props.flash.error)
  return msgs
})

onMounted(() => {
  try { isDark.value = localStorage.getItem('pay-theme') === 'dark' } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
})
function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('pay-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
}

const I = {
  home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  people: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.13a4 4 0 100-8 4 4 0 000 8zm6 2a4 4 0 10-8 0',
  doc: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  coin: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-9c-1.11 0-2.08.402-2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  play: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
  bank: 'M3 10h18M5 10v8m4-8v8m6-8v8m4-8v8M3 21h18M12 3l9 5H3l9-5z',
  chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
  shield: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
  link: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
  cog: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
  users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
  log: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
  db: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4',
  scale: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
}

// Navigasi dikelompokkan menurut alur kerja payroll, bukan menurut tabel database.
const groups = computed(() => [
  { title: null, items: [{ name: 'Dashboard', href: route('dashboard'), show: true, icon: I.home }] },
  { title: 'Kompensasi', items: [
    { name: 'Karyawan', href: route('employees.index'), show: has('employees.view'), icon: I.people },
    { name: 'Kontrak Kompensasi', href: route('contracts.index'), show: has('contracts.view'), icon: I.doc },
    { name: 'Golongan & Komponen', href: route('master.index'), show: has('regulations.view'), icon: I.coin },
  ] },
  { title: 'Proses Payroll', items: [
    { name: 'Input Variabel', href: route('inputs.index'), show: has('inputs.view'), icon: I.edit },
    { name: 'Kasbon', href: route('loans.index'), show: has('loans.view'), icon: I.coin },
    { name: 'Putaran Gaji', href: route('runs.index'), show: has('runs.view'), icon: I.play },
  ] },
  { title: 'Keuangan & Kepatuhan', items: [
    { name: 'Pengajuan Dana', href: route('funding.index'), show: has('funding.view'), icon: I.bank },
    { name: 'Setoran Pajak & BPJS', href: route('remittances.index'), show: has('remittances.view'), icon: I.scale },
    { name: 'Anggaran SDM', href: route('budgets.index'), show: has('budgets.view'), icon: I.chart },
    { name: 'Laporan', href: route('reports.index'), show: has('reports.view'), icon: I.doc },
  ] },
  { title: 'Kontrol', items: [
    { name: 'Jejak Audit', href: route('audit.index'), show: has('audit.view'), icon: I.shield },
    { name: 'Regulasi', href: route('regulations.index'), show: has('regulations.view'), icon: I.scale },
    { name: 'Integrasi API', href: route('integrations.index'), show: has('integrations.manage'), icon: I.link },
    { name: 'Saldo Awal Tahun', href: route('opening.index'), show: has('opening.manage'), icon: I.db },
  ] },
  { title: 'Layanan Mandiri', items: [{ name: 'Slip Gaji Saya', href: route('payslips.mine'), show: isStaff.value, icon: I.shield }] },
  { title: 'Sistem', items: [
    { name: 'Kelola Pengguna', href: route('users.index'), show: has('users.manage') || has('users.view'), badge: 'pending', icon: I.users },
    { name: 'Profil Perusahaan', href: route('settings.company'), show: has('settings.manage'), icon: I.cog },
    { name: 'Log Aktivitas', href: route('activity-log.index'), show: has('settings.manage'), icon: I.log },
    { name: 'Backup Data', href: route('backup.index'), show: has('settings.manage'), icon: I.db },
  ] },
].map(g => ({ ...g, items: g.items.filter(i => i.show) })).filter(g => g.items.length))

function isActive(href) {
  const path = new URL(href).pathname
  const url = page.url.split('?')[0]
  return url === path || url.startsWith(path + '/')
}
</script>

<template>
  <div class="min-h-screen bg-paper dark:bg-ink-950">
    <!-- Sidebar desktop -->
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-ink-900 lg:flex">
      <div class="flex h-16 shrink-0 items-center gap-2.5 px-6 border-b border-ink-800/70">
        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-seal-500 text-white font-serif font-bold text-lg">P</div>
        <div class="text-white">
          <p class="text-sm font-serif font-semibold leading-tight">PAYBRIDGE</p>
          <p class="text-[11px] text-ink-400 leading-tight tracking-wide">Payroll &amp; Budgeting</p>
        </div>
      </div>
      <nav class="mt-3 flex-1 overflow-y-auto px-3 pb-4">
        <div v-for="(g, gi) in groups" :key="gi" :class="g.title ? 'mt-4' : ''">
          <p v-if="g.title" class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-500">{{ g.title }}</p>
          <Link v-for="item in g.items" :key="item.name" :href="item.href"
            class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
            :class="isActive(item.href) ? 'bg-seal-500/20 text-seal-200' : 'text-ink-300 hover:bg-ink-800 hover:text-white'">
            <svg class="h-[18px] w-[18px] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" /></svg>
            {{ item.name }}
            <span v-if="item.badge === 'pending' && $page.props.pending_users > 0" class="ml-auto rounded-full bg-seal-500 px-2 py-0.5 text-[10px] font-bold text-white" title="Pengguna menunggu persetujuan">{{ $page.props.pending_users }}</span>
          </Link>
        </div>
      </nav>
      <div class="shrink-0 border-t border-ink-800/70 p-4">
        <div class="flex items-center gap-3">
          <Link :href="route('profile.edit')" class="-m-1 flex min-w-0 flex-1 items-center gap-3 rounded-md p-1 hover:bg-ink-800/70" title="Buka profil saya">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="36" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
              <p class="truncate text-xs text-ink-400">{{ user?.employee?.position_title || (user?.roles ?? [])[0] }}</p>
            </div>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="shrink-0 text-ink-400 hover:text-white" title="Keluar">
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
      <aside class="absolute inset-y-0 left-0 flex w-64 flex-col bg-ink-900 p-4 overflow-y-auto">
        <nav class="flex-1">
          <div v-for="(g, gi) in groups" :key="'mg'+gi" :class="g.title ? 'mt-3' : ''">
            <p v-if="g.title" class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-500">{{ g.title }}</p>
            <Link v-for="item in g.items" :key="'mi'+item.name" :href="item.href" class="block rounded-md px-3 py-2.5 text-sm font-medium" :class="isActive(item.href) ? 'bg-seal-500/20 text-seal-200' : 'text-ink-300 hover:bg-ink-800 hover:text-white'">{{ item.name }}</Link>
          </div>
        </nav>
        <div class="shrink-0 border-t border-ink-800/70 pt-4">
          <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-2 rounded-md px-3 py-2.5 text-sm font-medium text-ink-300 hover:bg-ink-800 hover:text-white">Keluar</Link>
        </div>
      </aside>
    </div>

    <!-- Content -->
    <div class="lg:pl-64">
      <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-ink-200 bg-paper/90 px-4 backdrop-blur lg:px-8 dark:border-ink-800 dark:bg-ink-950/90">
        <div class="flex items-center gap-3 min-w-0">
          <button class="text-ink-500 lg:hidden" @click="sidebarOpen = true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <h1 class="truncate text-lg font-serif font-semibold text-ink-900 dark:text-ink-100"><slot name="title">Dashboard</slot></h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <button @click="toggleTheme" class="rounded-md p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" title="Ganti tema">
            <svg v-if="!isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </button>
          <slot name="actions" />
        </div>
      </header>

      <div v-if="$page.props.flash?.success" role="status" class="mx-4 mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 lg:mx-8 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="bannerMessages.length && !dismissedError" role="alert" class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 lg:mx-8 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
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
