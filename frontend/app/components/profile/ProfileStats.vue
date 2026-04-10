<template>
  <div class="smgp-profile-stats">
    <div class="smgp-profile-stats__stat">
      <div class="smgp-profile-stats__icon-wrap smgp-profile-stats__icon-wrap--xp">
        <i class="icon-star" />
      </div>
      <div class="smgp-profile-stats__text">
        <span class="smgp-profile-stats__value">{{ data.xp_total?.toLocaleString() }}</span>
        <span class="smgp-profile-stats__label">{{ $t('profile.xp_total') }}</span>
      </div>
    </div>
    <div class="smgp-profile-stats__stat">
      <div class="smgp-profile-stats__icon-wrap smgp-profile-stats__icon-wrap--streak">
        <i class="icon-zap" />
      </div>
      <div class="smgp-profile-stats__text">
        <span class="smgp-profile-stats__value">{{ data.streak }} {{ $t('profile.days_short') }}</span>
        <span class="smgp-profile-stats__label">{{ $t('profile.streak_days') }}</span>
      </div>
    </div>
    <div class="smgp-profile-stats__stat">
      <div class="smgp-profile-stats__icon-wrap smgp-profile-stats__icon-wrap--courses">
        <i class="icon-book-open" />
      </div>
      <div class="smgp-profile-stats__text">
        <span class="smgp-profile-stats__value">{{ data.course_count }}</span>
        <span class="smgp-profile-stats__label">{{ $t('profile.enrolled_courses') }}</span>
      </div>
    </div>
    <div class="smgp-profile-stats__stat">
      <div class="smgp-profile-stats__icon-wrap smgp-profile-stats__icon-wrap--hours">
        <i class="icon-clock" />
      </div>
      <div class="smgp-profile-stats__text">
        <span class="smgp-profile-stats__value">{{ data.total_hours }}h</span>
        <span class="smgp-profile-stats__label">{{ $t('profile.total_hours') }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ProfileData } from '~/types/profile'

defineProps<{ data: ProfileData }>()
</script>

<style scoped lang="scss">
.smgp-profile-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
  margin-bottom: 1rem;

  @media (max-width: 700px) { grid-template-columns: repeat(2, 1fr); }

  &__stat {
    background: #fff;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  &__icon-wrap {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;

    &--xp      { background: #ecfdf5; color: #059669; }
    &--streak  { background: #fef3c7; color: #d97706; }
    &--courses { background: #ede9fe; color: #7c3aed; }
    &--hours   { background: #e0f2fe; color: #0284c7; }
  }
  &__text {
    min-width: 0;
  }
  &__value {
    display: block;
    font-size: 1.35rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
  }
  &__label {
    display: block;
    font-size: 0.72rem;
    color: #94a3b8;
    margin-top: 0.1rem;
  }
}
</style>
