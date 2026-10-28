<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserAvatar from '@/Components/UserAvatar.vue'

const page = usePage()
const sidebarOpen = ref(false)
const isDark = ref(false)
const dismissedError = ref(false)

const user = computed(() => page.props.auth?.user ?? null)
const has = (p) => (user.value?.permissions ?? []).includes(p)

const bannerMessages = computed(() => (page.props.flash?.error ? [page.props.flash.error] : []))

onMounted(() => {
  try { isDark.value = localStorage.getItem('profina-theme') === 'dark' } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
})
function toggleTheme() {
  isDark.value = !isDark.value
  try { localStorage.setItem('profina-theme', isDark.value ? 'dark' : 'light') } catch (e) { /* abaikan */ }
  document.documentElement.classList.toggle('dark', isDark.value)
}

const nav = computed(() => [
  { name: 'Pintu Tugas', href: route('portal', { stay: 1 }), match: '/portal', show: true, icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { name: 'Dashboard Inti', href: route('dashboard'), match: '/dashboard', show: !!user.value?.has_core, icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Pengguna & Penugasan', href: route('admin.users.index'), match: '/admin/users', show: has('users.view'), badge: true, icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { name: 'Audit', href: route('admin.audit'), match: '/admin/audit', show: has('audit.view'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
])

function isActive(match) {
  const url = page.url.split('?')[0]
  return url === match || url.startsWith(match + '/')
}
</script>

<template>
  <div class="min-h-screen bg-paper dark:bg-ink-950">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-ink-900 lg:flex">
      <div class="flex h-16 shrink-0 items-center gap-2.5 border-b border-ink-800/70 px-6">
        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-seal-500 font-serif text-lg font-bold text-ink-950">P</div>
        <div class="text-white">
          <p class="font-serif text-sm font-semibold leading-tight">PROFINA</p>
          <p class="text-[11px] leading-tight tracking-wide text-ink-400">Production · Marketing · Finance</p>
        </div>
      </div>
      <nav class="mt-3 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4">
        <template v-for="item in nav" :key="item.name">
          <Link v-if="item.show" :href="item.href"
            class="group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors"
            :class="isActive(item.match) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'">
            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" /></svg>
            {{ item.name }}
            <span v-if="item.badge && $page.props.pending_users > 0" class="ml-auto rounded-full bg-seal-500 px-2 py-0.5 text-[10px] font-bold text-ink-950" title="Pengguna menunggu persetujuan">{{ $page.props.pending_users }}</span>
          </Link>
        </template>
      </nav>
      <div class="shrink-0 border-t border-ink-800/70 p-4">
        <div class="flex items-center gap-3">
          <Link :href="route('profile.edit')" class="-m-1 flex min-w-0 flex-1 items-center gap-3 rounded-md p-1 hover:bg-ink-800/70" title="Profil saya">
            <UserAvatar :src="user?.avatar_url" :name="user?.name ?? '?'" :size="36" />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-white">{{ user?.name }}</p>
              <p class="truncate text-xs text-ink-400">{{ (user?.roles ?? [])[0] || user?.email }}</p>
            </div>
          </Link>
          <Link :href="route('logout')" method="post" as="button" class="shrink-0 text-ink-400 hover:text-white" title="Keluar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
          </Link>
        </div>
      </div>
    </aside>

    <div v-if="sidebarOpen" class="fixed inset-0 z-40 lg:hidden">
      <div class="absolute inset-0 bg-black/50" @click="sidebarOpen = false"></div>
      <aside class="absolute inset-y-0 left-0 flex w-64 flex-col overflow-y-auto bg-ink-900 p-4">
        <nav class="flex-1 space-y-0.5">
          <template v-for="item in nav" :key="'m-' + item.name">
            <Link v-if="item.show" :href="item.href" class="block rounded-md px-3 py-2.5 text-sm font-medium" :class="isActive(item.match) ? 'bg-seal-500/15 text-seal-300' : 'text-ink-300 hover:bg-ink-800 hover:text-white'">{{ item.name }}</Link>
          </template>
        </nav>
        <Link :href="route('logout')" method="post" as="button" class="mt-4 rounded-md px-3 py-2.5 text-left text-sm font-medium text-ink-300 hover:bg-ink-800 hover:text-white">Keluar</Link>
      </aside>
    </div>

    <div class="lg:pl-64">
      <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-ink-200 bg-paper/90 px-4 backdrop-blur lg:px-8 dark:border-ink-800 dark:bg-ink-950/90">
        <div class="flex min-w-0 items-center gap-3">
          <button class="text-ink-500 lg:hidden" @click="sidebarOpen = true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <h1 class="truncate font-serif text-lg font-semibold text-ink-900 dark:text-ink-100"><slot name="title">PROFINA</slot></h1>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <button @click="toggleTheme" class="rounded-md p-2 text-ink-500 hover:bg-ink-100 dark:text-ink-400 dark:hover:bg-ink-800" title="Ganti tema">
            <svg v-if="!isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
          </button>
          <slot name="actions" />
        </div>
      </header>

      <div v-if="$page.props.flash?.success" role="status" class="mx-4 mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 lg:mx-8 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">{{ $page.props.flash.success }}</div>
      <div v-if="bannerMessages.length && !dismissedError" role="alert" class="mx-4 mt-4 flex items-start justify-between gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 lg:mx-8 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
        <ul class="space-y-1"><li v-for="m in bannerMessages" :key="m">{{ m }}</li></ul>
        <button type="button" class="shrink-0 font-bold opacity-60 hover:opacity-100" title="Tutup" @click="dismissedError = true">&times;</button>
      </div>

      <main class="p-4 lg:p-8"><slot /></main>
    </div>
  </div>
</template>
