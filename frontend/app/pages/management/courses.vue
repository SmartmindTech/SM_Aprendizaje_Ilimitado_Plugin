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
            <i :class="'bi ' + option.icon + ' sm-admin-card__icon text-muted'" />
            <h6 class="card-title mb-1 text-muted">{{ option.title }}</h6>
            <p class="card-text text-muted small mb-0">{{ option.description }}</p>
          </div>
        </div>
        <a
          v-else-if="option.url && (option.url.startsWith('#') || option.url.includes('/spa.php'))"
          :href="option.url"
          class="card text-decoration-none shadow-sm sm-admin-card"
        >
          <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
            <i :class="['bi', option.icon, 'sm-admin-card__icon', 'sm-admin-card__icon--' + (option.icon_color || 'blue')]" />
            <h6 class="card-title mb-1">{{ option.title }}</h6>
            <p class="card-text text-muted small mb-0">{{ option.description }}</p>
          </div>
        </a>
        <NuxtLink
          v-else
          :to="option.vue_route || option.url"
          class="card text-decoration-none shadow-sm sm-admin-card"
        >
          <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
            <i :class="['bi', option.icon, 'sm-admin-card__icon', 'sm-admin-card__icon--' + (option.icon_color || 'blue')]" />
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

getCourseManagement().then((result) => {
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
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 1.25rem;
  width: 100%;
}

.sm-admin-card {
  flex: 0 0 calc(25% - 1.25rem);
  max-width: calc(25% - 1.25rem);
  min-width: 220px;
  border: 1px solid #e9ecef;
  border-radius: 0.75rem;
  background: #fff;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
@media (max-width: 992px) {
  .sm-admin-card {
    flex: 0 0 calc(50% - 1.25rem);
    max-width: calc(50% - 1.25rem);
  }
}
@media (max-width: 576px) {
  .sm-admin-card {
    flex: 0 0 100%;
    max-width: 100%;
  }
}
.sm-admin-card .card-body {
  min-height: 160px;
}
.sm-admin-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.10) !important;
}
.sm-admin-card .card-title {
  color: #1a1f35;
  font-weight: 600;
}
.sm-admin-card--disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.sm-admin-card__icon {
  font-size: 2rem;
  line-height: 1;
  display: inline-block;
  margin-bottom: 0.75rem;
}
.sm-admin-card__icon--green  { color: #16a34a; }
.sm-admin-card__icon--blue   { color: #2563eb; }
.sm-admin-card__icon--orange { color: #ea580c; }

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
