<template>
  <div class="smgp-topnav__user-area">
    <!-- Notifications bell (placeholder — no Moodle web service yet) -->
    <button
      type="button"
      class="smgp-notifications"
      :aria-label="$t('nav.notifications')"
    >
      <span class="smgp-notifications__icon" aria-hidden="true" />
      <span v-if="notifCount > 0" class="smgp-notifications__badge">{{ notifCount }}</span>
    </button>

    <!-- Language switcher: cycles through configured locales -->
    <button
      type="button"
      class="smgp-lang-toggle"
      :aria-label="$t('nav.language')"
      @click="cycleLocale"
    >
      <i class="icon-globe" aria-hidden="true" />
      <span>{{ currentLocaleLabel }}</span>
    </button>

    <!-- Dark mode toggle -->
    <button
      type="button"
      class="smgp-darkmode-toggle"
      :aria-label="$t('nav.darkmode')"
      @click="toggleDarkMode"
    >
      <span class="smgp-darkmode-toggle__icon" :data-mode="darkMode ? 'dark' : 'light'" aria-hidden="true" />
    </button>

    <!-- User avatar dropdown -->
    <div ref="dropdownRef" class="smgp-user-dropdown">
      <button
        type="button"
        class="smgp-user-dropdown__toggle"
        :aria-expanded="dropdownOpen"
        @click="dropdownOpen = !dropdownOpen"
      >
        <span class="smgp-user-dropdown__avatar" :title="authStore.fullname" :aria-label="authStore.fullname">
          {{ userInitials }}
        </span>
        <span class="smgp-user-dropdown__name">{{ authStore.fullname }}</span>
      </button>
      <div class="smgp-user-dropdown__menu" :class="{ show: dropdownOpen }">
        <NuxtLink to="/profile" class="smgp-user-dropdown__item" @click="dropdownOpen = false">
          {{ $t('nav.profile') }}
        </NuxtLink>
        <a :href="logoutUrl" class="smgp-user-dropdown__item">{{ $t('nav.logout') }}</a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const authStore = useAuthStore()
const { locale, locales, setLocale } = useI18n()

const dropdownRef = ref<HTMLElement | null>(null)
const dropdownOpen = ref(false)
const darkMode = ref(false)
const notifCount = ref(0) // Placeholder until a notifications endpoint exists.

const userInitials = computed(() => {
  const name = authStore.fullname || ''
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase()
  }
  return name.substring(0, 2).toUpperCase()
})

const currentLocaleLabel = computed(() => {
  const code = String(locale.value)
  if (code === 'pt_br') return 'PT'
  return code.toUpperCase()
})

const logoutUrl = computed(() => {
  const root = authStore.wwwroot || ''
  const sesskey = authStore.sesskey || ''
  return `${root}/login/logout.php?sesskey=${encodeURIComponent(sesskey)}`
})

function cycleLocale() {
  const order = (locales.value as Array<{ code: string }>).map(l => l.code)
  const idx = order.indexOf(String(locale.value))
  const next = order[(idx + 1) % order.length]
  if (next) setLocale(next as never)
}

function toggleDarkMode() {
  darkMode.value = !darkMode.value
  applyDarkMode()
}

function applyDarkMode() {
  if (typeof document === 'undefined') return
  document.documentElement.classList.toggle('smgp-dark', darkMode.value)
  try {
    localStorage.setItem('smgp-theme', darkMode.value ? 'dark' : 'light')
  } catch (_) {
    // localStorage may be unavailable in some contexts; ignore.
  }
}

function onClickOutside(e: MouseEvent) {
  if (!dropdownOpen.value) return
  const el = dropdownRef.value
  if (el && !el.contains(e.target as Node)) {
    dropdownOpen.value = false
  }
}

onMounted(() => {
  // Restore dark mode preference.
  try {
    darkMode.value = localStorage.getItem('smgp-theme') === 'dark'
  } catch (_) {
    darkMode.value = false
  }
  applyDarkMode()
  document.addEventListener('click', onClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside)
})
</script>
