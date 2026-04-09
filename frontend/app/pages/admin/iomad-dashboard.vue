<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="smgp-mgmt-page">
    <header class="smgp-mgmt-page__header d-flex align-items-start gap-3">
      <button
        type="button"
        class="btn smgp-back-btn mt-1"
        @click="$router.back()"
      >
        <i class="icon-arrow-left" />
      </button>
      <div class="flex-grow-1">
        <h1 class="smgp-mgmt-page__title">{{ $t('iomad.heading') }}</h1>
        <p class="smgp-mgmt-page__desc">
          {{ $t('iomadDashboard.desc') }}
          <template v-if="data?.companyname"> · <strong>{{ data.companyname }}</strong></template>
        </p>
      </div>
    </header>

    <!-- Empty state -->
    <p v-if="!groups.length" class="text-muted">{{ $t('iomad.no_cards') }}</p>

    <template v-else>
      <!-- Category tabs -->
      <div class="smgp-mgmt-page__tabs" role="tablist">
        <button
          v-for="(group, idx) in groups"
          :key="group.key"
          type="button"
          role="tab"
          class="smgp-mgmt-page__tab"
          :class="{ 'smgp-mgmt-page__tab--active': activeTab === idx }"
          :aria-selected="activeTab === idx"
          @click="activeTab = idx"
        >
          <i :class="group.icon" class="smgp-mgmt-page__tab-icon" />
          <span>{{ group.title }}</span>
        </button>
      </div>

      <!-- Cards for the active category -->
      <div class="smgp-mgmt-grid">
        <a
          v-for="opt in groups[activeTab]?.options ?? []"
          :key="opt.title + opt.url"
          :href="opt.url"
          class="smgp-mgmt-card"
        >
          <span class="smgp-mgmt-card__icon">
            <i :class="iconForGroup(groups[activeTab]?.key)" />
          </span>
          <span class="smgp-mgmt-card__text">
            <span class="smgp-mgmt-card__title">{{ opt.title }}</span>
            <span class="smgp-mgmt-card__desc">{{ opt.description || groups[activeTab]?.title }}</span>
          </span>
        </a>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

definePageMeta({ middleware: 'auth' })

const { getIomadDashboard } = useAdminApi()

interface IomadOption {
  url: string
  icon: string
  title: string
  description: string
}
interface IomadCategory {
  key: string
  icon: string
  title: string
  options: IomadOption[]
}
interface IomadDashboardData {
  companyname: string
  cards: unknown[]
  categories: IomadCategory[]
}

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<IomadDashboardData | null>(null)
const activeTab = ref(0)

// Map IOMAD category keys to a Lucide icon. The fallback covers any
// future category the backend may add.
const IOMAD_CATEGORY_ICONS: Record<string, string> = {
  configuration:  'icon-cog',
  companies:      'icon-building-2',
  users:          'icon-users',
  courses:        'icon-graduation-cap',
  licenses:       'icon-rulers',
  competences:    'icon-box',
  emailtemplates: 'icon-mail',
  shop:           'icon-shopping-bag',
  reports:        'icon-bar-chart',
}

const iconForGroup = (key: string | undefined): string =>
  (key && IOMAD_CATEGORY_ICONS[key]) || 'icon-link'

// Render every category that has at least one option. Empty groups
// (which can happen for categories the user has no permission on) are
// dropped so the tab bar isn't cluttered with dead pills.
const groups = computed(() =>
  (data.value?.categories ?? []).filter(c => c.options.length > 0),
)

getIomadDashboard().then((result) => {
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data as IomadDashboardData
  }
})
</script>
