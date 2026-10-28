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
const isStaff = computed(() => !!user.value?.employee)

const bannerMessages = computed(() => {
  const msgs = []
  if (page.props.flash?.error) msgs.push(page.props.flash.error)
  return msgs
})

onMounted(() => {
  try { isDark.value = localStorage.getItem('sdm-theme') === 'dark' } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
})
function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('sdm-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
}

const managementNav = computed(() => [
  { name: 'Dashboard', href: route('dashboard'), show: true, icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Karyawan', href: route('employees.index'), show: has('employees.view'), icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.13a4 4 0 100-8 4 4 0 000 8zm6 2a4 4 0 10-8 0' },
  { name: 'Departemen', href: route('departments.index'), show: has('departments.view'), icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3m4-14h6m-6 4h6m-6 4h6' },
  { name: 'Absensi', href: route('attendances.index'), show: has('attendances.view'), icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'Cuti & Izin', href: route('leaves.index'), show: has('leaves.view'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { name: 'Hari Libur', href: route('holidays.index'), show: has('holidays.view'), icon: 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9' },
  { name: 'Payroll', href: route('payroll.index'), show: has('payroll.view'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-9c-1.11 0-2.08.402-2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'Integrasi Payroll', href: route('integrations.payroll'), show: has('integrations.manage'), icon: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1' },
  { name: 'Kinerja / KPI', href: route('performance.index'), show: has('performance.view'), icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' },
  { name: 'Rekrutmen', href: route('recruitment.index'), show: has('recruitment.view'), icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { name: 'Pelatihan', href: route('trainings.index'), show: has('trainings.view'), icon: 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422A12.083 12.083 0 0112 21a12.083 12.083 0 01-6.16-10.422L12 14z' },
  { name: 'Dokumen', href: route('documents.index'), show: has('documents.view'), icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { name: 'Pengumuman', href: route('announcements.index'), show: true, icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z' },
])

const selfServiceNav = computed(() => [
  { name: 'Absen Saya', href: route('attendances.my-history'), icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { name: 'Cuti Saya', href: route('leaves.mine'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { name: 'Slip Gaji Saya', href: route('payroll.mine'), icon: 'M9 14l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
  { name: 'Slip Gaji (Payroll)', href: route('payroll.mine-external'), icon: 'M9 14l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
  { name: 'Penilaian Saya', href: route('performance.mine'), icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10' },
  { name: 'Pelatihan Saya', href: route('trainings.mine'), icon: 'M12 14l9-5-9-5-9 5 9 5z' },
])

const adminNav = computed(() => [
  { name: 'Kelola Pengguna', href: route('users.index'), show: has('users.manage') || has('users.view'), badge: 'pending', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { name: 'Log Aktivitas', href: route('activity-log.index'), show: has('settings.manage'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  { name: 'Backup Data', href: route('backup.index'), show: has('settings.manage'), icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' },
])

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
        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-seal-500 text-ink-950 font-serif font-bold text-lg">S</div>
        <div class="text-white">
          <p class="text-sm font-serif font-semibold leading-tight">SDM HRIS</p>
          <p class="text-[11px] text-ink-400 leading-tight tracking-wide">Manajemen SDM</p>
        </div>
      </div>
      <nav class="mt-3 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
        <template v-for="item in managementNav" :key="item.name">
          <Link
            v-if="item.show"
            :href="item.href"
            class="group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
            :class="isActive(item.href) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'"
          >
            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
            {{ item.name }}
          </Link>
        </template>

        <div v-if="isStaff" class="mt-4 pt-4 border-t border-ink-800/70">
          <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Layanan Mandiri</p>
          <Link
            v-for="item in selfServiceNav" :key="item.name" :href="item.href"
            class="group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
            :class="isActive(item.href) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'"
          >
            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
            {{ item.name }}
          </Link>
        </div>

        <template v-for="item in adminNav" :key="item.name">
          <div v-if="item.show && adminNav.some(i => i.show)" class="mt-4 pt-4 border-t border-ink-800/70 first:mt-0 first:border-0 first:pt-0"></div>
        </template>
        <template v-for="item in adminNav" :key="'a-'+item.name">
          <Link
            v-if="item.show"
            :href="item.href"
            class="group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
            :class="isActive(item.href) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'"
          >
            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
            {{ item.name }}
            <span v-if="item.badge === 'pending' && $page.props.pending_users > 0" class="ml-auto rounded-full bg-seal-500 px-2 py-0.5 text-[10px] font-bold text-ink-950" title="Pengguna menunggu persetujuan">{{ $page.props.pending_users }}</span>
          </Link>
        </template>
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
        <nav class="flex-1 space-y-0.5">
          <template v-for="item in managementNav" :key="'m-'+item.name">
            <Link v-if="item.show" :href="item.href" class="block rounded-md px-3 py-2.5 text-sm font-medium" :class="isActive(item.href) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'">{{ item.name }}</Link>
          </template>
          <div v-if="isStaff" class="mt-4 pt-4 border-t border-ink-800/70">
            <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">Layanan Mandiri</p>
            <Link v-for="item in selfServiceNav" :key="'sm-'+item.name" :href="item.href" class="block rounded-md px-3 py-2.5 text-sm font-medium" :class="isActive(item.href) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'">{{ item.name }}</Link>
          </div>
          <template v-for="item in adminNav" :key="'am-'+item.name">
            <Link v-if="item.show" :href="item.href" class="block rounded-md px-3 py-2.5 text-sm font-medium" :class="isActive(item.href) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'">{{ item.name }}</Link>
          </template>
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
