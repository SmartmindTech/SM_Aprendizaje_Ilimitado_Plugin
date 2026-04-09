<template>
  <div class="smgp-profile-header">
    <!-- Info button (top-right) -->
    <button
      ref="infoBtnEl"
      type="button"
      class="smgp-profile-header__info-btn"
      :class="{ 'is-active': infoOpen }"
      :aria-label="$t('profile.info_button_aria')"
      :aria-expanded="infoOpen"
      @click="infoOpen = !infoOpen"
    >
      <i class="icon-info" />
    </button>

    <!-- Info dropdown -->
    <transition name="smgp-info-fade">
      <div v-if="infoOpen" ref="infoPanelEl" class="smgp-profile-header__info-panel" role="dialog">
        <div class="smgp-profile-header__info-head">
          <h4 class="smgp-profile-header__info-title">{{ $t('profile.info_title') }}</h4>
          <button
            type="button"
            class="smgp-profile-header__info-close"
            :aria-label="$t('profile.info_close')"
            @click="infoOpen = false"
          >
            <i class="icon-x" />
          </button>
        </div>
        <p class="smgp-profile-header__info-intro">{{ $t('profile.info_intro') }}</p>
        <ul class="smgp-profile-header__info-list">
          <li class="smgp-profile-header__info-row">
            <i class="smgp-profile-header__info-icon icon-check-circle" />
            <span class="smgp-profile-header__info-text">{{ $t('profile.info_xp_activity_title') }}</span>
          </li>
          <li class="smgp-profile-header__info-row">
            <i class="smgp-profile-header__info-icon icon-graduation-cap" />
            <span class="smgp-profile-header__info-text">{{ $t('profile.info_xp_course_title') }}</span>
          </li>
          <li class="smgp-profile-header__info-row">
            <i class="smgp-profile-header__info-icon icon-log-in" />
            <span class="smgp-profile-header__info-text">{{ $t('profile.info_xp_login_title') }}</span>
          </li>
          <li class="smgp-profile-header__info-row">
            <i class="smgp-profile-header__info-icon icon-trophy" />
            <span class="smgp-profile-header__info-text">{{ $t('profile.info_xp_achievement_title') }}</span>
          </li>
        </ul>
      </div>
    </transition>

    <!-- Avatar with level ring -->
    <div class="smgp-profile-header__avatar-wrap">
      <svg class="smgp-profile-header__ring" viewBox="0 0 160 160">
        <circle class="smgp-profile-header__ring-track" cx="80" cy="80" r="72" />
        <circle
          class="smgp-profile-header__ring-progress" cx="80" cy="80" r="72"
          :stroke-dasharray="ringCircumference"
          :stroke-dashoffset="ringDashOffset"
        />
      </svg>
      <img :src="data.avatarurl" :alt="data.fullname" class="smgp-profile-header__avatar">
      <div class="smgp-profile-header__level-badge">
        <span class="smgp-profile-header__level-num">{{ data.level }}</span>
        <span class="smgp-profile-header__level-label">{{ $t('profile.level') }}</span>
      </div>
    </div>

    <!-- Identity + XP bar -->
    <div class="smgp-profile-header__info">
      <h1 class="smgp-profile-header__name">{{ data.fullname }}</h1>
      <p v-if="data.has_department" class="smgp-profile-header__department">
        <i class="icon-building-2" /> {{ data.department }}
      </p>
      <p class="smgp-profile-header__meta">
        <i class="icon-calendar" /> {{ $t('profile.member_since') }} {{ data.joindate }}
      </p>

      <div class="smgp-profile-header__xp">
        <div class="smgp-profile-header__xp-row">
          <span class="smgp-profile-header__xp-amount">
            {{ $t('profile.xp_progress', { current: data.xp_into_level, next: data.xp_for_next }) }}
          </span>
          <span class="smgp-profile-header__xp-next">
            {{ $t('profile.xp_to_next', { xp: data.xp_to_next }) }}
          </span>
        </div>
        <div class="smgp-profile-header__xp-bar">
          <div
            class="smgp-profile-header__xp-fill"
            :style="{ width: data.level_progress_pct + '%' }"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import type { ProfileData } from '~/types/profile'

const props = defineProps<{ data: ProfileData }>()

// ── Level ring (SVG circumference math) ──
const ringCircumference = 2 * Math.PI * 72
const ringDashOffset = computed(() => {
  const pct = props.data.level_progress_pct ?? 0
  return ringCircumference - (ringCircumference * pct) / 100
})

// ── Info dropdown ──
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
  position: relative;
  display: flex;
  gap: 2rem;
  align-items: center;
  padding: 2rem;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  margin-bottom: 1.5rem;

  @media (max-width: 720px) {
    flex-direction: column;
    text-align: center;
  }

  // ── Info button ──
  &__info-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid #d1fae5;
    background: #ecfdf5;
    color: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    z-index: 5;

    i { font-size: 1.05rem; }

    &:hover,
    &.is-active {
      background: #10b981;
      border-color: #10b981;
      color: #fff;
      transform: scale(1.05);
    }
  }

  // ── Info dropdown panel ──
  &__info-panel {
    position: absolute;
    top: 3.75rem;
    right: 1rem;
    width: 300px;
    max-width: calc(100vw - 2rem);
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
    padding: 1.1rem 1.1rem 1rem;
    z-index: 10;
    text-align: left;
  }
  &__info-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
  }
  &__info-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
  }
  &__info-close {
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;

    &:hover { background: #f1f5f9; color: #1e293b; }
  }
  &__info-intro {
    font-size: 0.78rem;
    color: #64748b;
    margin: 0 0 0.75rem;
    line-height: 1.4;
  }
  &__info-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  }
  &__info-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.6rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #f1f5f9;
  }
  &__info-icon {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    background: #ecfdf5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
  }
  &__info-text {
    font-size: 0.82rem;
    font-weight: 500;
    color: #1e293b;
    line-height: 1.3;
  }

  // ── Avatar / ring ──
  &__avatar-wrap {
    position: relative;
    width: 160px;
    height: 160px;
    flex-shrink: 0;
  }
  &__ring {
    position: absolute;
    inset: 0;
    width: 160px;
    height: 160px;
    transform: rotate(-90deg);
  }
  &__ring-track {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 6;
  }
  &__ring-progress {
    fill: none;
    stroke: #10b981;
    stroke-width: 6;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.6s ease;
  }
  &__avatar {
    position: absolute;
    inset: 12px;
    width: calc(100% - 24px);
    height: calc(100% - 24px);
    border-radius: 50%;
    object-fit: cover;
  }
  &__level-badge {
    position: absolute;
    bottom: -4px;
    right: -4px;
    background: #10b981;
    color: #fff;
    border: 4px solid #fff;
    border-radius: 50%;
    width: 56px;
    height: 56px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
  }
  &__level-num {
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1;
  }
  &__level-label {
    font-size: 0.55rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.1rem;
    opacity: 0.9;
  }

  // ── Identity ──
  &__info {
    flex: 1;
    min-width: 0;
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

  // ── XP bar ──
  &__xp {
    margin-top: 1rem;
  }
  &__xp-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 0.4rem;
    font-size: 0.85rem;

    @media (max-width: 720px) { flex-direction: column; gap: 0.2rem; }
  }
  &__xp-amount {
    font-weight: 600;
    color: #1e293b;
  }
  &__xp-next {
    color: #64748b;
  }
  &__xp-bar {
    height: 10px;
    background: #f1f5f9;
    border-radius: 999px;
    overflow: hidden;
  }
  &__xp-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #34d399);
    border-radius: 999px;
    transition: width 0.6s ease;
  }
}

.smgp-info-fade-enter-active,
.smgp-info-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.smgp-info-fade-enter-from,
.smgp-info-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
