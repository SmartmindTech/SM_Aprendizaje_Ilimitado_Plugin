<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="smgp-profile">
    <div class="smgp-profile__header">
      <img :src="data.avatarurl" :alt="data.fullname" class="smgp-profile__avatar">
      <div class="smgp-profile__header-info">
        <h1 class="smgp-profile__name">{{ data.fullname }}</h1>
        <p v-if="data.has_department" class="smgp-profile__department">
          <i class="icon-building" /> {{ data.department }}
        </p>
        <p class="smgp-profile__meta">
          <i class="icon-calendar" /> {{ $t('profile.member_since') || 'Member since' }} {{ data.joindate }}
        </p>
      </div>
    </div>

    <div class="smgp-profile__stats">
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.course_count }}</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.enrolled_courses') || 'Enrolled' }}</span>
      </div>
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.completed_count }}</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.completed_courses') || 'Completed' }}</span>
      </div>
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.total_hours }}h</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.total_hours') || 'Hours' }}</span>
      </div>
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.streak }}</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.streak_days') || 'Day streak' }}</span>
      </div>
    </div>

    <div class="smgp-profile__chart">
      <h3 class="smgp-profile__chart-title">
        {{ $t('profile.weekly_activity') || 'This week\'s activity' }}
      </h3>
      <div class="smgp-profile__bars">
        <div
          v-for="(day, idx) in data.week_activity"
          :key="idx"
          class="smgp-profile__bar-column"
          :class="{ 'is-today': day.istoday, 'is-past': day.ispast, 'is-future': !day.ispast }"
        >
          <div class="smgp-profile__bar" :style="{ height: day.height + '%' }" />
          <span class="smgp-profile__bar-label">{{ day.day }}</span>
          <span class="smgp-profile__bar-count">{{ day.count }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useMoodleAjax } from '~/composables/api_calls/useMoodleAjax'

definePageMeta({ middleware: ['auth'] })

const { call } = useMoodleAjax()
const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

async function fetchProfile() {
  loading.value = true
  error.value = null
  const result = await call('local_sm_graphics_plugin_get_profile_data', { userid: 0 })
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
  loading.value = false
}

fetchProfile()
</script>

<style scoped lang="scss">
.smgp-profile {
  max-width: 960px;
  margin: 2rem auto;
  padding: 0 1rem;

  &__header {
    display: flex;
    gap: 2rem;
    align-items: center;
    padding: 2rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
  }
  &__avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e2e8f0;
  }
  &__name {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.5rem;
  }
  &__department,
  &__meta {
    color: #64748b;
    margin: 0.25rem 0;
    font-size: 0.9rem;
    i { margin-right: 0.35rem; }
  }
  &__stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
    @media (max-width: 600px) { grid-template-columns: repeat(2, 1fr); }
  }
  &__stat {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }
  &__stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #10b981;
  }
  &__stat-label {
    display: block;
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 0.25rem;
  }
  &__chart {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }
  &__chart-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 1rem;
  }
  &__bars {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    height: 180px;
    gap: 0.5rem;
  }
  &__bar-column {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
    position: relative;
  }
  &__bar {
    width: 100%;
    min-height: 4px;
    background: #10b981;
    border-radius: 6px 6px 0 0;
    transition: height 0.4s ease-out;
  }
  &__bar-column.is-today .smgp-profile__bar { background: #059669; }
  &__bar-column.is-future .smgp-profile__bar { background: #e2e8f0; }
  &__bar-label {
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 600;
  }
  &__bar-count {
    font-size: 0.7rem;
    color: #94a3b8;
  }
}
</style>
