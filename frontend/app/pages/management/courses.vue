<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="sm-coursemanagement p-4 w-100">
    <h2 :class="data.companyname ? 'mb-1' : 'mb-4'">
      {{ data.heading || 'Course Management' }}
    </h2>
    <p v-if="data.companyname" class="text-muted mb-4">{{ data.companyname }}</p>

    <!-- Action cards -->
    <div class="sm-admin-cards">
      <template v-for="option in data.options" :key="option.title">
        <div v-if="option.disabled" class="card shadow-sm sm-admin-card sm-admin-card--disabled">
          <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
            <i :class="'fa ' + option.icon + ' fa-2x mb-2 text-muted'" />
            <h6 class="card-title mb-1 text-muted">{{ option.title }}</h6>
            <p class="card-text text-muted small mb-0">{{ option.description }}</p>
          </div>
        </div>
        <NuxtLink
          v-else
          :to="option.vue_route || option.url"
          class="card text-decoration-none shadow-sm sm-admin-card"
        >
          <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
            <i :class="'fa ' + option.icon + ' fa-2x mb-2 text-primary'" />
            <h6 class="card-title mb-1">{{ option.title }}</h6>
            <p class="card-text text-muted small mb-0">{{ option.description }}</p>
          </div>
        </NuxtLink>
      </template>
    </div>

    <!-- Company overview table -->
    <template v-if="data.hascompanies">
      <h4 class="mt-5 mb-3">{{ $t('coursemgmt.companies') || 'Companies' }}</h4>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle sm-company-table">
          <thead class="table-light">
            <tr>
              <th>{{ $t('coursemgmt.companies') || 'Companies' }}</th>
              <th class="text-center">{{ $t('coursemgmt.courses_col') || 'Courses' }}</th>
              <th class="text-center">{{ $t('coursemgmt.users_col') || 'Users' }}</th>
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

onMounted(async () => {
  const result = await getCourseManagement()
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
})
</script>

<style scoped>
.sm-admin-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
  width: 100%;
}
@media (min-width: 992px) {
  .sm-admin-cards {
    grid-template-columns: repeat(5, 1fr);
  }
}

.sm-admin-card {
  border: 1px solid #dee2e6;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sm-admin-card .card-body {
  min-height: 140px;
}
.sm-admin-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12) !important;
}
.sm-admin-card .card-title {
  color: #1a1f35;
}
.sm-admin-card--disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.sm-company-table {
  width: 100%;
}
.sm-company-table th {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #64748b;
}
</style>
