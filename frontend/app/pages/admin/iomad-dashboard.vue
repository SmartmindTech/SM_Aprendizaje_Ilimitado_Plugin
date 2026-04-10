<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-success" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="smgp-mgmt-page">
    <header class="smgp-iomad-header">
      <button type="button" class="btn smgp-back-btn" @click="$router.back()">
        <i class="icon-arrow-left" />
      </button>
      <div class="smgp-iomad-header__text">
        <h1 class="smgp-mgmt-page__title mb-0">{{ $t('iomad.heading') }}</h1>
        <p class="smgp-mgmt-page__desc mb-0">{{ $t('iomadDashboard.desc') }}</p>
      </div>
      <div v-if="companies.length" class="smgp-iomad-header__selector" :title="$t('iomadDashboard.select_company_hint')">
        <i class="bi bi-building" />
        <select v-model.number="selectedCompanyId" class="form-control form-control-sm" @change="onCompanyChange">
          <option v-for="c in companies" :key="c.id" :value="c.id">
            {{ c.name }}
          </option>
        </select>
      </div>
    </header>

    <!-- Empty state -->
    <p v-if="!groups.length && !loading" class="text-muted mt-3">{{ $t('iomad.no_cards') }}</p>

    <template v-if="groups.length">
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
          <i :class="iconForGroup(group.key)" class="smgp-mgmt-page__tab-icon" />
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

const { call } = useMoodleAjax()

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
interface Company {
  id: number
  name: string
  shortname: string
}
interface IomadDashboardData {
  companyid: number
  companyname: string
  companies: Company[]
  cards: unknown[]
  categories: IomadCategory[]
}

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<IomadDashboardData | null>(null)
const activeTab = ref(0)
const selectedCompanyId = ref(0)
const companies = ref<Company[]>([])

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

const groups = computed(() =>
  (data.value?.categories ?? []).filter(c => c.options.length > 0),
)

async function fetchDashboard(companyid = 0) {
  loading.value = true
  error.value = null
  const result = await call<IomadDashboardData>(
    'local_sm_graphics_plugin_get_iomad_dashboard_data',
    { companyid },
  )
  loading.value = false
  if (result.error) {
    error.value = result.error
    return
  }
  data.value = result.data ?? null
  if (data.value) {
    companies.value = data.value.companies ?? []
    if (!selectedCompanyId.value && data.value.companyid) {
      selectedCompanyId.value = data.value.companyid
    }
  }
  activeTab.value = 0
}

function onCompanyChange() {
  fetchDashboard(selectedCompanyId.value)
}

fetchDashboard()
</script>

<style scoped lang="scss">
.smgp-iomad-header {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-bottom: 1.25rem;

  &__text {
    flex: 1;
  }

  &__selector {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #f0fdf4;
    border: 1px solid rgba(16, 185, 129, 0.25);
    border-radius: 10px;
    padding: 0.4rem 0.75rem;
    cursor: help;
    i {
      color: #10b981;
      font-size: 1rem;
    }
    select {
      width: 200px;
      background-color: #fff !important;
      cursor: pointer;
      border-radius: 6px;
      border-color: #d1fae5;
      font-size: 0.82rem;
      font-weight: 600;
      color: #1e293b;
      &:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
      }
    }
  }
}
</style>
