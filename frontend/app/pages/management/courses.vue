<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="smgp-mgmt-page">
    <header class="smgp-mgmt-page__header">
      <h1 class="smgp-mgmt-page__title">{{ $t('management.courses.title') }}</h1>
      <p class="smgp-mgmt-page__desc">
        {{ $t('management.courses.desc') }}
        <template v-if="data.companyname"> · <strong>{{ data.companyname }}</strong></template>
      </p>
    </header>

    <!-- Quick action cards -->
    <h2 class="smgp-mgmt-page__section-title">
      <i class="icon-zap" aria-hidden="true" />
      {{ $t('management.courses.section_actions') }}
    </h2>
    <div class="smgp-mgmt-grid">
      <NuxtLink to="/admin/create-course" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-square-pen" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.courses.card_create') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.courses.card_create_desc') }}</span>
        </span>
      </NuxtLink>
      <NuxtLink to="/management/categories" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-folder" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.courses.card_categories') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.courses.card_categories_desc') }}</span>
        </span>
      </NuxtLink>
      <NuxtLink to="/admin/company-limits" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-building-2" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.courses.card_limits') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.courses.card_limits_desc') }}</span>
        </span>
      </NuxtLink>
      <NuxtLink to="/admin/courseloader" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-cloud-upload" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.courses.card_loader') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.courses.card_loader_desc') }}</span>
        </span>
      </NuxtLink>
      <NuxtLink to="/admin/restore" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-rotate-ccw" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.courses.card_restore') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.courses.card_restore_desc') }}</span>
        </span>
      </NuxtLink>
    </div>

    <!-- Company overview table -->
    <template v-if="data.hascompanies">
      <h2 class="smgp-mgmt-page__section-title">
        <i class="icon-building-2" aria-hidden="true" />
        {{ $t('management.courses.section_companies') }}
      </h2>
      <div class="table-responsive">
        <table class="smgp-mgmt-table">
          <thead>
            <tr>
              <th>{{ $t('coursemgmt.companies') }}</th>
              <th class="text-center">{{ $t('coursemgmt.courses_col') }}</th>
              <th class="text-center">{{ $t('coursemgmt.users_col') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="company in data.companies" :key="company.name">
              <td>
                <strong>{{ company.name }}</strong>
                <span v-if="company.shortname" class="text-muted small ms-1">({{ company.shortname }})</span>
              </td>
              <td class="text-center">{{ company.coursecount }}</td>
              <td class="text-center">
                {{ company.usercount }}
                <template v-if="company.maxusers"> / {{ company.maxusers }}</template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { getCourseManagement } = useManagementApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

getCourseManagement().then((result) => {
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
})
</script>
