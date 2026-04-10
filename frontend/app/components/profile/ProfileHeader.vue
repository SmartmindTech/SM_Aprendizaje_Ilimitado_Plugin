<template>
  <div class="smgp-profile-header">
    <!-- Top row: Avatar + Identity -->
    <div class="smgp-profile-header__top">
      <div class="smgp-profile-header__avatar-wrap">
        <img :src="data.avatarurl" :alt="data.fullname" class="smgp-profile-header__avatar">
      </div>
      <div class="smgp-profile-header__identity">
        <h1 class="smgp-profile-header__name">{{ data.fullname }}</h1>
        <p v-if="data.has_department" class="smgp-profile-header__department">{{ data.department }}</p>
        <p class="smgp-profile-header__meta">
          {{ data.course_count }} {{ $t('profile.enrolled_courses') }}
          <span class="smgp-profile-header__sep">&middot;</span>
          {{ data.completed_count }} {{ $t('profile.completed_courses') }}
        </p>
      </div>

      <!-- Info button -->
      <button
        ref="infoBtnEl"
        type="button"
        class="smgp-profile-header__info-btn"
        :class="{ 'is-active': infoOpen }"
        @click="infoOpen = !infoOpen"
      >
        <i class="icon-info" />
      </button>

      <!-- Info dropdown -->
      <transition name="smgp-info-fade">
        <div v-if="infoOpen" ref="infoPanelEl" class="smgp-profile-header__info-panel" role="dialog">
          <div class="smgp-profile-header__info-head">
            <h4 class="smgp-profile-header__info-title">{{ $t('profile.info_title') }}</h4>
            <button type="button" class="smgp-profile-header__info-close" @click="infoOpen = false">
              <i class="icon-x" />
            </button>
          </div>
          <p class="smgp-profile-header__info-intro">{{ $t('profile.info_intro') }}</p>
          <ul class="smgp-profile-header__info-list">
            <li class="smgp-profile-header__info-row">
              <i class="smgp-profile-header__info-icon icon-check-circle" />
              <span>{{ $t('profile.info_xp_activity_title') }}</span>
            </li>
            <li class="smgp-profile-header__info-row">
              <i class="smgp-profile-header__info-icon icon-graduation-cap" />
              <span>{{ $t('profile.info_xp_course_title') }}</span>
            </li>
            <li class="smgp-profile-header__info-row">
              <i class="smgp-profile-header__info-icon icon-log-in" />
              <span>{{ $t('profile.info_xp_login_title') }}</span>
            </li>
            <li class="smgp-profile-header__info-row">
              <i class="smgp-profile-header__info-icon icon-trophy" />
              <span>{{ $t('profile.info_xp_achievement_title') }}</span>
            </li>
          </ul>
        </div>
      </transition>
    </div>

    <!-- Stats row (below avatar, full width) -->
    <div class="smgp-profile-header__stats">
      <div class="smgp-profile-header__stat">
        <div class="smgp-profile-header__stat-icon smgp-profile-header__stat-icon--xp">
          <i class="icon-star" />
        </div>
        <div>
          <span class="smgp-profile-header__stat-value">{{ data.xp_total?.toLocaleString() }}</span>
          <span class="smgp-profile-header__stat-label">{{ $t('profile.xp_total') }}</span>
        </div>
      </div>
      <div class="smgp-profile-header__stat">
        <div class="smgp-profile-header__stat-icon smgp-profile-header__stat-icon--streak">
          <i class="icon-zap" />
        </div>
        <div>
          <span class="smgp-profile-header__stat-value">{{ data.streak }} {{ $t('profile.days_short') }}</span>
          <span class="smgp-profile-header__stat-label">{{ $t('profile.streak_days') }}</span>
        </div>
      </div>
      <div class="smgp-profile-header__stat">
        <div class="smgp-profile-header__stat-icon smgp-profile-header__stat-icon--courses">
          <i class="icon-book-open" />
        </div>
        <div>
          <span class="smgp-profile-header__stat-value">{{ data.course_count }}</span>
          <span class="smgp-profile-header__stat-label">{{ $t('profile.enrolled_courses') }}</span>
        </div>
      </div>
      <div class="smgp-profile-header__stat">
        <div class="smgp-profile-header__stat-icon smgp-profile-header__stat-icon--hours">
          <i class="icon-clock" />
        </div>
        <div>
          <span class="smgp-profile-header__stat-value">{{ data.total_hours }}h</span>
          <span class="smgp-profile-header__stat-label">{{ $t('profile.total_hours') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import type { ProfileData } from '~/types/profile'

defineProps<{ data: ProfileData }>()

const infoOpen = ref(false)
const infoBtnEl = ref<HTMLElement | null>(null)
const infoPanelEl = ref<HTMLElement | null>(null)

function handleClickOutside(event: MouseEvent) {
  if (!infoOpen.value) return
  const target = event.target as Node
  if (
    infoPanelEl.value && !infoPanelEl.value.contains(target)
    && infoBtnEl.value && !infoBtnEl.value.contains(target)
  ) {
    infoOpen.value = false
  }
}
function handleEsc(event: KeyboardEvent) {
  if (event.key === 'Escape') infoOpen.value = false
}
onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  document.addEventListener('keydown', handleEsc)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  document.removeEventListener('keydown', handleEsc)
})
</script>

<style scoped lang="scss">
.smgp-profile-header {
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  margin-bottom: 1rem;

  // ── Top: avatar + identity ──
  &__top {
    position: relative;
    display: flex;
    gap: 1.25rem;
    align-items: center;
    margin-bottom: 1.25rem;

    @media (max-width: 640px) {
      flex-direction: column;
      text-align: center;
    }
  }
  &__avatar-wrap { flex-shrink: 0; }
  &__avatar {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e2e8f0;
  }
  &__identity { flex: 1; min-width: 0; }
  &__name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
  }
  &__department {
    color: #64748b;
    margin: 0.2rem 0 0;
    font-size: 0.82rem;
  }
  &__meta {
    color: #94a3b8;
    margin: 0.1rem 0 0;
    font-size: 0.78rem;
  }
  &__sep { margin: 0 0.3rem; }

  // ── Stats row (full width, below avatar) ──
  &__stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    @media (max-width: 640px) { grid-template-columns: repeat(2, 1fr); }
  }
  &__stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    border: 1px solid transparent;

    &:nth-child(1) { background: #ecfdf5; border-color: #d1fae5; } // XP — green
    &:nth-child(2) { background: #fffbeb; border-color: #fef3c7; } // Streak — amber
    &:nth-child(3) { background: #f5f3ff; border-color: #ede9fe; } // Courses — purple
    &:nth-child(4) { background: #eff6ff; border-color: #dbeafe; } // Hours — blue
  }
  &__stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    &--xp      { background: #d1fae5; color: #059669; }
    &--streak  { background: #fde68a; color: #b45309; }
    &--courses { background: #ddd6fe; color: #6d28d9; }
    &--hours   { background: #bfdbfe; color: #1d4ed8; }
  }
  &__stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
  }
  &__stat-label {
    display: block;
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 0.05rem;
  }

  // ── Info button ──
  &__info-btn {
    position: absolute;
    top: 0;
    right: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    i { font-size: 0.85rem; }
    &:hover, &.is-active {
      background: #10b981;
      border-color: #10b981;
      color: #fff;
    }
  }
  &__info-panel {
    position: absolute;
    top: 2.5rem;
    right: 0;
    width: 280px;
    max-width: calc(100vw - 2rem);
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
    padding: 1rem;
    z-index: 10;
    text-align: left;
  }
  &__info-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.4rem;
  }
  &__info-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
  }
  &__info-close {
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    &:hover { background: #f1f5f9; color: #1e293b; }
  }
  &__info-intro {
    font-size: 0.75rem;
    color: #64748b;
    margin: 0 0 0.5rem;
    line-height: 1.4;
  }
  &__info-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }
  &__info-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.5rem;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 500;
    color: #1e293b;
  }
  &__info-icon {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: #ecfdf5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
  }
}

.smgp-info-fade-enter-active,
.smgp-info-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.smgp-info-fade-enter-from,
.smgp-info-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
