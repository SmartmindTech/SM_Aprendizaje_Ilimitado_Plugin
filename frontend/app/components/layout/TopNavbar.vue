<template>
  <nav class="smgp-topnav" aria-label="SmartMind navigation">
    <!-- Left: logo -->
    <div class="smgp-topnav__left">
      <NuxtLink to="/dashboard" class="smgp-topnav__logo">
        <img src="/img/smartmind_logo.png" :alt="$t('app.name')">
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
  // Student items
  if (!authStore.isManager && !authStore.isAdmin) {
    return [
      { key: 'myhome',         to: '/dashboard',           label: t('nav.dashboard'),  matches: ['/dashboard'] },
      { key: 'home',           to: '/catalogue',           label: t('nav.catalogue'),  matches: ['/catalogue'] },
      { key: 'sm-mycourses',   to: '/courses',             label: t('nav.courses'),    matches: ['/courses'] },
      { key: 'sm-gradescerts', to: '/grades-certificates', label: t('nav.grades'),     matches: ['/grades-certificates'] },
      { key: 'sm-profile',     to: '/profile',             label: t('nav.profile'),    matches: ['/profile'] },
    ]
  }

  // Manager items
  if (authStore.isManager && !authStore.isAdmin) {
    return [
      { key: 'sm-usermanagement',  to: '/management/users',     label: t('nav.usermgmt'),    matches: ['/management/users'] },
      { key: 'sm-coursemanagement', to: '/management/courses',  label: t('nav.coursemgmt'),  matches: ['/management/courses'] },
      { key: 'sm-categories',      to: '/management/categories', label: t('nav.categories'), matches: ['/management/categories'] },
      { key: 'sm-othermanagement', to: '/admin/iomad-dashboard', label: t('nav.othermgmt'), matches: ['/admin/iomad-dashboard'] },
      { key: 'sm-statistics',      to: '/statistics',           label: t('nav.statistics'),  matches: ['/statistics'] },
    ]
  }

  // Admin items (no "My space" — admins don't have a personal dashboard)
  return [
    { key: 'home',                to: '/catalogue',             label: t('nav.catalogue'),   matches: ['/catalogue'] },
    { key: 'sm-coursemanagement', to: '/management/courses',    label: t('nav.coursemgmt'),  matches: ['/management/courses'] },
    { key: 'siteadminnode',       to: '/admin/settings',        label: t('nav.siteadmin'),   matches: ['/admin/settings'] },
    { key: 'iomaddashboard',      to: '/admin/iomad-dashboard', label: t('nav.iomad'),       matches: ['/admin/iomad-dashboard'] },
  ]
})

function isActive(item: NavItem) {
  return item.matches.some(p => route.path === p || route.path.startsWith(p + '/'))
}
</script>
