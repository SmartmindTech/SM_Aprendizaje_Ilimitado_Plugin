<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="sm-iomad-dashboard p-4 w-100">
    <h2 :class="data.companyname ? 'mb-1' : 'mb-4'">
      {{ $t('iomad.heading') }}
    </h2>
    <p v-if="data.companyname" class="text-muted mb-4">{{ data.companyname }}</p>

    <!-- Admin cards -->
    <div class="sm-admin-cards">
      <a
        v-for="card in data.cards || []"
        :key="card.key"
        :href="card.url"
        class="card text-decoration-none shadow-sm sm-admin-card"
      >
        <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-4">
          <i :class="['bi', card.icon, 'sm-admin-card__icon', 'sm-admin-card__icon--' + (card.icon_color || 'blue')]" />
          <h6 class="card-title mb-0 mt-3">{{ card.title }}</h6>
        </div>
      </a>
      <div v-if="!data.cards?.length" class="text-muted">{{ $t('iomad.no_cards') }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { getIomadDashboard } = useAdminApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

getIomadDashboard().then((result) => {
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
})
</script>

<style scoped>
.sm-iomad-dashboard {
  max-width: 1200px;
  margin: 0 auto;
}

.sm-admin-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
  width: 100%;
}
@media (max-width: 992px) {
  .sm-admin-cards {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 576px) {
  .sm-admin-cards {
    grid-template-columns: 1fr;
  }
}

.sm-admin-card {
  border: 1px solid #e9ecef;
  border-radius: 0.75rem;
  background: #fff;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  color: #1a1f35;
}
.sm-admin-card .card-body {
  min-height: 170px;
}
.sm-admin-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.10) !important;
}
.sm-admin-card .card-title {
  font-weight: 600;
}

.sm-admin-card__icon {
  font-size: 2.25rem;
  line-height: 1;
  display: inline-block;
}
.sm-admin-card__icon--green  { color: #16a34a; }
.sm-admin-card__icon--blue   { color: #2563eb; }
.sm-admin-card__icon--orange { color: #ea580c; }
.sm-admin-card__icon--violet { color: #7c3aed; }
</style>
