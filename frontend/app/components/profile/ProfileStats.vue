<template>
  <div class="smgp-profile-stats">
    <div
      class="smgp-profile-stats__stat"
      :data-tooltip="$t('profile.tooltip_enrolled')"
    >
      <i class="smgp-profile-stats__icon icon-book-open" />
      <span class="smgp-profile-stats__value">{{ data.course_count }}</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.enrolled_courses') }}</span>
    </div>
    <div
      class="smgp-profile-stats__stat"
      :data-tooltip="$t('profile.tooltip_completed')"
    >
      <i class="smgp-profile-stats__icon icon-award" />
      <span class="smgp-profile-stats__value">{{ data.completed_count }}</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.completed_courses') }}</span>
    </div>
    <div
      class="smgp-profile-stats__stat"
      :data-tooltip="$t('profile.tooltip_hours')"
    >
      <i class="smgp-profile-stats__icon icon-clock" />
      <span class="smgp-profile-stats__value">{{ data.total_hours }}h</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.total_hours') }}</span>
    </div>
    <div
      class="smgp-profile-stats__stat"
      :data-tooltip="$t('profile.tooltip_streak')"
    >
      <i class="smgp-profile-stats__icon icon-zap" />
      <span class="smgp-profile-stats__value">{{ data.streak }}</span>
      <span class="smgp-profile-stats__label">{{ $t('profile.streak_days') }}</span>
    </div>
    <div
      class="smgp-profile-stats__stat smgp-profile-stats__stat--xp"
      :data-tooltip="$t('profile.tooltip_xp')"
    >
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
    position: relative;
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem 0.75rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    cursor: help;

    &--xp {
      background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    }

    // ── Tooltip on hover ──
    // Pure CSS tooltip driven by the data-tooltip attribute. Shows above
    // the card with a small arrow underneath. Hidden by default with
    // opacity + visibility so the transition is animatable.
    &::after {
      content: attr(data-tooltip);
      position: absolute;
      bottom: calc(100% + 10px);
      left: 50%;
      transform: translateX(-50%) translateY(4px);
      background: #0f172a;
      color: #fff;
      font-size: 0.72rem;
      font-weight: 500;
      line-height: 1.35;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      width: max-content;
      max-width: 220px;
      white-space: normal;
      text-align: center;
      pointer-events: none;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
      box-shadow: 0 6px 16px rgba(15, 23, 42, 0.2);
      z-index: 20;
    }

    // Triangle pointing down to the card.
    &::before {
      content: '';
      position: absolute;
      bottom: calc(100% + 4px);
      left: 50%;
      transform: translateX(-50%) translateY(4px);
      border: 6px solid transparent;
      border-top-color: #0f172a;
      pointer-events: none;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
      z-index: 20;
    }

    &:hover::after,
    &:hover::before,
    &:focus-within::after,
    &:focus-within::before {
      opacity: 1;
      visibility: visible;
      transform: translateX(-50%) translateY(0);
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
