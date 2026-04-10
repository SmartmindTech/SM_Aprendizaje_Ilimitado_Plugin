<template>
  <div class="smgp-profile-missions">
    <div class="smgp-profile-missions__head">
      <h3 class="smgp-profile-missions__title">{{ $t('profile.missions_title') }}</h3>
    </div>

    <div class="smgp-profile-missions__tabs">
      <button
        class="smgp-profile-missions__tab"
        :class="{ 'is-active': missionsTab === 'daily' }"
        @click="missionsTab = 'daily'"
      >
        {{ $t('profile.missions_daily') }}
      </button>
      <button
        class="smgp-profile-missions__tab"
        :class="{ 'is-active': missionsTab === 'weekly' }"
        @click="missionsTab = 'weekly'"
      >
        {{ $t('profile.missions_weekly') }}
      </button>
    </div>

    <ul v-if="currentMissions.length" class="smgp-profile-missions__list">
      <li
        v-for="m in currentMissions" :key="m.code"
        class="smgp-profile-missions__mission"
        :class="{
          'is-claimable': m.claimable,
          'is-claimed':   m.claimed,
        }"
      >
        <div class="smgp-profile-missions__mission-row">
          <div class="smgp-profile-missions__mission-icon">
            <i :class="iconClass(m.icon)" />
          </div>
          <div class="smgp-profile-missions__mission-body">
            <div class="smgp-profile-missions__mission-head-row">
              <span class="smgp-profile-missions__mission-text">{{ m.name }}</span>
              <span class="smgp-profile-missions__mission-reward">+{{ m.xp_reward }} XP</span>
            </div>
            <div class="smgp-profile-missions__mission-sub-row">
              <p
                v-if="m.description"
                class="smgp-profile-missions__mission-desc"
              >
                {{ m.description }}
              </p>
              <span class="smgp-profile-missions__mission-progress">
                {{ m.progress }} / {{ m.target }}
              </span>
            </div>
            <div class="smgp-profile-missions__mission-bar">
              <div
                class="smgp-profile-missions__mission-fill"
                :class="{ 'is-full': m.progress >= m.target }"
                :style="{ width: m.progress_pct + '%' }"
              />
            </div>
          </div>
        </div>

        <div class="smgp-profile-missions__mission-action">
          <button
            v-if="m.claimed"
            type="button"
            class="smgp-profile-missions__claim-btn smgp-profile-missions__claim-btn--done"
            disabled
          >
            <i class="icon-check" /> {{ $t('profile.mission_claimed') }}
          </button>
          <button
            v-else-if="m.claimable"
            type="button"
            class="smgp-profile-missions__claim-btn"
            :disabled="claimingCode === m.code"
            @click="onClaim(m.code)"
          >
            <span v-if="claimingCode === m.code" class="spinner-border spinner-border-sm" />
            <template v-else>
              <i class="icon-zap" /> {{ $t('profile.mission_claim') }}
            </template>
          </button>
          <button
            v-else
            type="button"
            class="smgp-profile-missions__claim-btn smgp-profile-missions__claim-btn--locked"
            disabled
          >
            {{ $t('profile.mission_in_progress') }}
          </button>
        </div>
      </li>
    </ul>

    <div v-else class="smgp-profile-missions__empty">
      {{ $t('profile.missions_empty') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useProfileApi } from '~/composables/api_calls/useProfileApi'
import type { ProfileMission, ClaimMissionResult } from '~/types/profile'

const props = defineProps<{
  daily: ProfileMission[]
  weekly: ProfileMission[]
}>()

const emit = defineEmits<{
  (e: 'claimed', payload: ClaimMissionResult): void
}>()

import { useProfileStore } from '~/stores/profile'

const profileStore = useProfileStore()

const missionsTab = ref<'daily' | 'weekly'>('daily')
const claimingCode = ref<string | null>(null)

const currentMissions = computed<ProfileMission[]>(() =>
  missionsTab.value === 'daily' ? props.daily : props.weekly
)

// Maps mission icon identifiers (stored in PHP catalog) to Lucide classes.
function iconClass(icon: string): string {
  const map: Record<string, string> = {
    'log-in':       'icon-log-in',
    'check-circle': 'icon-check-circle',
    zap:            'icon-zap',
    graduation:     'icon-graduation-cap',
    star:           'icon-star',
    trophy:         'icon-trophy',
  }
  return map[icon] || 'icon-zap'
}

async function onClaim(code: string) {
  if (claimingCode.value) return
  claimingCode.value = code
  try {
    const claim = await profileStore.claimMission(code)
    if (!claim) {
      console.error('[smgp] claimMission failed')
      return
    }
    if (!claim.success) {
      console.warn('[smgp] claim rejected:', claim.reason)
    }
    emit('claimed', claim)
  } finally {
    claimingCode.value = null
  }
}
</script>

<style scoped lang="scss">
.smgp-profile-missions {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.25rem 1.5rem;
  height: auto;

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
  }
  &__title {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
  }
  &__tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
  }
  &__tab {
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    border-radius: 999px;
    padding: 0.3rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;

    &.is-active {
      background: #10b981;
      border-color: #10b981;
      color: #fff;
    }
  }
  &__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
  }
  &__mission {
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 0.55rem 0.75rem;
    background: #f8fafc;
    transition: background 0.2s, border-color 0.2s;

    &.is-claimable {
      background: #ecfdf5;
      border-color: #6ee7b7;
    }
    &.is-claimed {
      background: #f0fdf4;
      border-color: #d1fae5;
      opacity: 0.8;
    }
  }
  &__mission-row {
    display: flex;
    gap: 0.65rem;
    align-items: flex-start;
  }
  &__mission-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: #d1fae5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
  }
  &__mission-body {
    flex: 1;
    min-width: 0;
  }
  &__mission-head-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 0.15rem;
  }
  &__mission-text {
    font-size: 0.85rem;
    color: #1e293b;
    font-weight: 600;
    line-height: 1.2;
  }
  &__mission-reward {
    font-size: 0.75rem;
    color: #059669;
    font-weight: 700;
  }
  // Description on the left, progress counter on the right — both share
  // a single row so the card stays compact.
  &__mission-sub-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.3rem;
  }
  &__mission-desc {
    margin: 0;
    font-size: 0.72rem;
    color: #64748b;
    line-height: 1.3;
    flex: 1;
    min-width: 0;
  }
  &__mission-progress {
    font-size: 0.7rem;
    color: #94a3b8;
    flex-shrink: 0;
    white-space: nowrap;
  }
  &__mission-bar {
    height: 5px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
  }
  &__mission-fill {
    height: 100%;
    background: #10b981;
    border-radius: 999px;
    transition: width 0.4s ease;

    &.is-full {
      background: linear-gradient(90deg, #10b981, #34d399);
    }
  }
  &__mission-action {
    margin-top: 0.45rem;
    display: flex;
    justify-content: flex-end;
  }
  &__claim-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: none;
    background: #10b981;
    color: #fff;
    border-radius: 999px;
    padding: 0.3rem 0.85rem;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s;
    min-width: 100px;
    justify-content: center;

    &:hover:not(:disabled) {
      background: #059669;
      transform: translateY(-1px);
    }
    &:disabled {
      cursor: default;
    }

    &--done {
      background: transparent;
      color: #059669;
      border: 1px solid #6ee7b7;
    }
    &--locked {
      background: transparent;
      color: #94a3b8;
      border: 1px solid #e2e8f0;
    }

    i { font-size: 0.95rem; }
  }
  &__empty {
    padding: 1.25rem;
    text-align: center;
    color: #94a3b8;
    background: #f8fafc;
    border-radius: 10px;
    font-size: 0.85rem;
  }
}
</style>
