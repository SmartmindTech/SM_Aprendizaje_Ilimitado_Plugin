<template>
  <div class="sm-statistics p-4 w-100">
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">{{ $t('app.loading') }}</span>
      </div>
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else-if="data">
      <h2 class="mb-4">{{ data.heading || $t('nav.statistics') }}</h2>

      <!-- Summary cards -->
      <div class="sm-stat-cards mb-5">
        <div v-for="card in data.cards" :key="card.label" class="sm-stat-card">
          <div class="sm-stat-card__icon">
            <i :class="'fa ' + card.icon" />
          </div>
          <div class="sm-stat-card__value">{{ card.value }}</div>
          <div class="sm-stat-card__label">{{ card.label }}</div>
        </div>
      </div>

      <!-- Weekly charts -->
      <div class="sm-stat-charts">
        <div class="sm-stat-chart-card">
          <h4 class="sm-stat-chart-card__title">
            {{ data.completions_title || 'Weekly Completions' }}
          </h4>
          <div v-if="data.weekly_completions" class="sm-stat-chart-card__bars">
            <div
              v-for="week in data.weekly_completions"
              :key="week.label"
              class="d-flex align-items-center gap-2 mb-2"
            >
              <span class="small text-muted" style="width:80px">{{ week.label }}</span>
              <div class="progress flex-grow-1" style="height:20px">
                <div
                  class="progress-bar bg-primary"
                  :style="{ width: getBarWidth(week.value, data.weekly_completions) + '%' }"
                >
                  {{ week.value }}
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="sm-stat-chart-card">
          <h4 class="sm-stat-chart-card__title">
            {{ data.active_users_title || 'Weekly Active Users' }}
          </h4>
          <div v-if="data.weekly_active_users" class="sm-stat-chart-card__bars">
            <div
              v-for="week in data.weekly_active_users"
              :key="week.label"
              class="d-flex align-items-center gap-2 mb-2"
            >
              <span class="small text-muted" style="width:80px">{{ week.label }}</span>
              <div class="progress flex-grow-1" style="height:20px">
                <div
                  class="progress-bar bg-success"
                  :style="{ width: getBarWidth(week.value, data.weekly_active_users) + '%' }"
                >
                  {{ week.value }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { getStatistics } = useManagementApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

const getBarWidth = (value: number, items: Array<{ value: number }>) => {
  const max = Math.max(...items.map(i => i.value), 1)
  return Math.round((value / max) * 100)
}

getStatistics().then((result) => {
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
})
</script>

<style scoped>
.sm-statistics {
  width: 100%;
  max-width: 100%;
}

.sm-stat-cards {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 1.5rem;
}
@media (max-width: 1200px) {
  .sm-stat-cards { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
  .sm-stat-cards { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
  .sm-stat-cards { grid-template-columns: 1fr; }
}

.sm-stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  transition: box-shadow 0.2s ease-in-out, transform 0.2s ease-in-out;
}
.sm-stat-card:hover {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.sm-stat-card__icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}
.sm-stat-card__icon .fa {
  font-size: 1.25rem;
  color: #374151;
}

.sm-stat-card__value {
  font-size: 2rem;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
  margin-bottom: 0.25rem;
}

.sm-stat-card__label {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.sm-stat-charts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  width: 100%;
}
@media (max-width: 992px) {
  .sm-stat-charts { grid-template-columns: 1fr; }
}

.sm-stat-chart-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  min-width: 0;
  overflow: hidden;
}
.sm-stat-chart-card__title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
</style>
