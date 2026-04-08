<template>
  <div class="smgp-admin-settings">
    <header class="smgp-admin-settings__header">
      <h1 class="smgp-admin-settings__title">{{ $t('adminSettings.title') }}</h1>
      <p class="smgp-admin-settings__desc">{{ $t('adminSettings.desc') }}</p>
    </header>

    <div class="smgp-admin-settings__grid">
      <NuxtLink
        v-for="section in sections"
        :key="section.to"
        :to="section.to"
        class="smgp-admin-settings__card"
      >
        <span class="smgp-admin-settings__card-icon">
          <i :class="section.icon" />
        </span>
        <span class="smgp-admin-settings__card-title">{{ section.label }}</span>
        <span class="smgp-admin-settings__card-desc">{{ section.desc }}</span>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

definePageMeta({ middleware: 'auth' })

const { t } = useI18n()

const sections = computed(() => [
  {
    to: '/admin/iomad-dashboard',
    icon: 'icon-building-2',
    label: t('adminSettings.iomadLabel'),
    desc: t('adminSettings.iomadDesc'),
  },
  {
    to: '/management/users',
    icon: 'icon-users',
    label: t('adminSettings.usersLabel'),
    desc: t('adminSettings.usersDesc'),
  },
  {
    to: '/management/courses',
    icon: 'icon-graduation-cap',
    label: t('adminSettings.coursesLabel'),
    desc: t('adminSettings.coursesDesc'),
  },
  {
    to: '/management/categories',
    icon: 'icon-folder',
    label: t('adminSettings.categoriesLabel'),
    desc: t('adminSettings.categoriesDesc'),
  },
  {
    to: '/admin/company-limits',
    icon: 'icon-gauge',
    label: t('adminSettings.limitsLabel'),
    desc: t('adminSettings.limitsDesc'),
  },
  {
    to: '/admin/updates',
    icon: 'icon-cloud-upload',
    label: t('adminSettings.updatesLabel'),
    desc: t('adminSettings.updatesDesc'),
  },
  {
    to: '/admin/restore',
    icon: 'icon-rotate-ccw',
    label: t('adminSettings.restoreLabel'),
    desc: t('adminSettings.restoreDesc'),
  },
  {
    to: '/statistics',
    icon: 'icon-bar-chart',
    label: t('adminSettings.statsLabel'),
    desc: t('adminSettings.statsDesc'),
  },
])
</script>

<style lang="scss" scoped>
.smgp-admin-settings {
  padding: 2rem 2.5rem;
  max-width: 1200px;
  margin: 0 auto;

  &__header {
    margin-bottom: 2rem;
  }

  &__title {
    font-size: 2rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 0.5rem;
  }

  &__desc {
    color: #6b7280;
    margin: 0;
  }

  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1rem;
  }

  &__card {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 1.25rem;
    background: #fff;
    border: 1px solid #f3f4f6;
    border-radius: 16px;
    text-decoration: none;
    color: inherit;
    transition: transform 0.15s ease, box-shadow 0.15s ease;

    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
      text-decoration: none;
      color: inherit;
    }
  }

  &__card-icon {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 12px;
    margin-bottom: 0.4rem;

    i {
      font-size: 1.25rem;
      color: #10b981;
    }
  }

  &__card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1a1a1a;
  }

  &__card-desc {
    font-size: 0.85rem;
    color: #6b7280;
  }
}
</style>
