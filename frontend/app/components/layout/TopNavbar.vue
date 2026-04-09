<template>
  <nav class="smgp-topnav" aria-label="SmartMind navigation">
    <!-- Left: logo -->
    <div class="smgp-topnav__left">
      <NuxtLink to="/dashboard" class="smgp-topnav__logo">
        <img :src="logoUrl" :alt="$t('app.name')">
      </NuxtLink>
    </div>

    <!-- Center: role-based pill nav -->
    <div class="smgp-topnav__center">
      <NuxtLink
        v-for="item in navItems"
        :key="item.key"
        :to="item.to"
        :data-key="item.key"
        class="smgp-topnav__item"
        :class="{ 'smgp-topnav__item--active': isActive(item) }"
      >
        <span class="smartmind-nav-icon" aria-hidden="true">
          <i class="fa fa-fw" />
        </span>
        <span class="smgp-topnav__item-text">{{ item.label }}</span>
      </NuxtLink>
    </div>

    <!-- Right: user area -->
    <div class="smgp-topnav__right">
      <LayoutUserArea />
    </div>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import logoUrl from '~/assets/img/smartmind_logo.png'

interface NavItem {
  key: string
  to: string
  label: string
  // Item is considered active when the current route path starts with any of these.
  matches: string[]
}

const authStore = useAuthStore()
const route = useRoute()
const { t } = useI18n()

const navItems = computed<NavItem[]>(() => {
  // Student items — Mis cursos and Notas y diplomas were merged into the
  // Profile page, so the student nav is intentionally compact: home,
  // catalogue and profile. Profile keeps the legacy /courses + /grades-
  // certificates routes in `matches` so the pill stays active even if a
  // bookmark or older link still points there.
  if (!authStore.isManager && !authStore.isAdmin) {
    return [
      { key: 'myhome',     to: '/dashboard', label: t('nav.dashboard'), matches: ['/dashboard'] },
      { key: 'home',       to: '/catalogue', label: t('nav.catalogue'), matches: ['/catalogue'] },
      { key: 'sm-profile', to: '/profile',   label: t('nav.profile'),   matches: ['/profile', '/courses', '/grades-certificates'] },
    ]
  }

  // Manager items — the manager surface is intentionally minimal:
  // user management (where the company-config quick actions live) and
  // statistics. Courses + categories admin pages are still reachable
  // by direct URL but no longer exposed in the top nav.
  if (authStore.isManager && !authStore.isAdmin) {
    return [
      { key: 'sm-usermanagement', to: '/management/users', label: t('nav.usermgmt'),   matches: ['/management/users', '/management/upload', '/management/courses', '/management/categories'] },
      { key: 'sm-statistics',     to: '/statistics',       label: t('nav.statistics'), matches: ['/statistics'] },
    ]
  }

  // Admin items (no "My space" — admins don't have a personal dashboard)
  return [
    { key: 'home',                to: '/catalogue',             label: t('nav.catalogue'),       matches: ['/catalogue'] },
    { key: 'sm-coursemanagement', to: '/management/courses',    label: t('nav.coursemgmt'),      matches: ['/management/courses', '/admin/courseloader', '/admin/restore', '/courses/create'] },
    { key: 'siteadminnode',       to: '/admin/settings',        label: t('nav.siteadmin'),       matches: ['/admin/settings', '/admin/company-limits', '/admin/updates'] },
    { key: 'iomaddashboard',      to: '/admin/iomad-dashboard', label: t('nav.iomaddashboard'),  matches: ['/admin/iomad-dashboard'] },
  ]
})

function isActive(item: NavItem) {
  return item.matches.some(p => route.path === p || route.path.startsWith(p + '/'))
}
</script>
