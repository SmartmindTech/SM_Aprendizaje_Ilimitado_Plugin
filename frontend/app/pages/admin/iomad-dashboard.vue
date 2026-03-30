<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="sm-othermanagement p-4 w-100">
    <h2 :class="data.companyname ? 'mb-1' : 'mb-4'">
      {{ data.heading || 'IOMAD Dashboard' }}
    </h2>
    <p v-if="data.companyname" class="text-muted mb-4">{{ data.companyname }}</p>

    <!-- Category cards -->
    <div id="sm-othermgmt-accordion" class="sm-admin-cards mb-3">
      <div
        v-for="(category, idx) in data.categories"
        :key="category.key"
        class="card shadow-sm sm-admin-card sm-category-toggle"
        :class="{ active: expandedKey === category.key }"
        :aria-expanded="expandedKey === category.key ? 'true' : 'false'"
        :aria-controls="'collapse-' + category.key"
        role="button"
        @click="toggleCategory(category.key)"
      >
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
          <i :class="'fa ' + category.icon + ' fa-2x mb-2 text-primary'" />
          <h6 class="card-title mb-0">{{ category.title }}</h6>
        </div>
      </div>
    </div>

    <!-- Collapsible sub-option panels -->
    <div id="sm-othermgmt-panels">
      <div
        v-for="category in data.categories"
        :key="'panel-' + category.key"
        :id="'collapse-' + category.key"
        class="sm-category-panel"
        :class="{ show: expandedKey === category.key }"
      >
        <div v-if="expandedKey === category.key" class="p-3">
          <h5 class="mb-3">{{ category.title }}</h5>
          <div class="sm-admin-cards">
            <template v-for="option in category.options" :key="option.title">
              <div v-if="option.disabled" class="card shadow-sm sm-admin-card sm-admin-card--disabled">
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
                  <i :class="'fa ' + option.icon + ' fa-2x mb-2 text-muted'" />
                  <h6 class="card-title mb-1 text-muted">{{ option.title }}</h6>
                  <p class="card-text text-muted small mb-0">{{ option.description }}</p>
                </div>
              </div>
              <a
                v-else
                :href="option.url"
                class="card text-decoration-none shadow-sm sm-admin-card"
              >
                <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
                  <i :class="'fa ' + option.icon + ' fa-2x mb-2 text-primary'" />
                  <h6 class="card-title mb-1">{{ option.title }}</h6>
                  <p class="card-text text-muted small mb-0">{{ option.description }}</p>
                </div>
              </a>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'auth' })

const { getIomadDashboard } = useAdminApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const expandedKey = ref<string | null>(null)

const toggleCategory = (key: string) => {
  expandedKey.value = expandedKey.value === key ? null : key
}

onMounted(async () => {
  const result = await getIomadDashboard()
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
    // Expand first category by default
    if (data.value?.categories?.length) {
      expandedKey.value = data.value.categories[0].key
    }
  }
})
</script>

<style scoped>
.sm-othermanagement {
  width: 100%;
  max-width: 100%;
}

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

.sm-category-toggle {
  cursor: pointer;
  user-select: none;
}
.sm-category-toggle[aria-expanded="true"] {
  border-color: var(--bs-primary, #6366f1);
  box-shadow: 0 0 0 2px var(--bs-primary, #6366f1) !important;
}

.sm-category-panel {
  display: none;
}
.sm-category-panel.show {
  display: block;
}
.sm-category-panel .sm-admin-cards {
  margin-top: 0;
}
</style>
