<template>
  <div class="smgp-profile-stats">
    <div class="smgp-profile-stats__stat">
      <i class="smgp-profile-stats__icon icon-book-open" />
      <span class="smgp-profile-stats__value">{{ data.course_count }}</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.enrolled_courses') }}</span>
    </div>
    <div class="smgp-profile-stats__stat">
      <i class="smgp-profile-stats__icon icon-award" />
      <span class="smgp-profile-stats__value">{{ data.completed_count }}</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.completed_courses') }}</span>
    </div>
    <div class="smgp-profile-stats__stat">
      <i class="smgp-profile-stats__icon icon-clock" />
      <span class="smgp-profile-stats__value">{{ data.total_hours }}h</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.total_hours') }}</span>
    </div>
    <div class="smgp-profile-stats__stat">
      <i class="smgp-profile-stats__icon icon-zap" />
      <span class="smgp-profile-stats__value">{{ data.streak }}</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.streak_days') }}</span>
    </div>
    <div class="smgp-profile-stats__stat smgp-profile-stats__stat--xp">
      <i class="smgp-profile-stats__icon icon-star" />
      <span class="smgp-profile-stats__value">{{ data.xp_total }}</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.xp_total') }}</span>
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
  grid-template-columns: repeat(5, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;

  @media (max-width: 900px) { grid-template-columns: repeat(3, 1fr); }
  @media (max-width: 600px) { grid-template-columns: repeat(2, 1fr); }

  &__stat {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem 0.75rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;

    &--xp {
      background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    }
  }
  &__icon {
    font-size: 1.4rem;
    color: #10b981;
    margin-bottom: 0.15rem;
  }
  &__value {
    display: block;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
  }
  &__label {
    display: block;
    font-size: 0.8rem;
    color: #64748b;
  }
}
</style>
