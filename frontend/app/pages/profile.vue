<template>
  <div class="smgp-profile-page">
    <!-- Top-level tabs -->
    <div class="smgp-profile-tabs">
      <button
        v-for="tab in tabs" :key="tab.key"
        class="smgp-profile-tabs__btn"
        :class="{ 'smgp-profile-tabs__btn--active': activeTab === tab.key }"
        @click="setTab(tab.key)"
      >
        <i :class="tab.icon" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Tab: Profile overview -->
    <div v-if="activeTab === 'profile'">
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">{{ $t('app.loading') }}</span>
        </div>
      </div>

      <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

      <template v-else-if="data">
        <!-- Header (avatar + identity + stats below) -->
        <ProfileHeader :data="data" />

        <!-- Row 1: Level+Streak+RecentXP (left) | Missions (right) -->
        <div class="smgp-profile-page__row-half">
          <div class="smgp-profile-page__card smgp-profile-page__level">
            <div class="smgp-profile-page__level-top">
              <!-- Level ring -->
              <div class="smgp-profile-page__level-head">
                <div class="smgp-profile-page__level-ring-wrap">
                  <svg class="smgp-profile-page__level-ring" viewBox="0 0 80 80">
                    <circle class="smgp-profile-page__level-track" cx="40" cy="40" r="34" />
                    <circle
                      class="smgp-profile-page__level-progress" cx="40" cy="40" r="34"
                      :stroke-dasharray="levelCircumference"
                      :stroke-dashoffset="levelDashOffset"
                    />
                  </svg>
                  <span class="smgp-profile-page__level-num">{{ data.level }}</span>
                </div>
                <div class="smgp-profile-page__level-info">
                  <span class="smgp-profile-page__level-title">{{ $t('profile.level') }} {{ data.level }}</span>
                  <span class="smgp-profile-page__level-xp">{{ data.xp_into_level }} / {{ data.xp_for_next }} XP</span>
                </div>
              </div>
              <!-- Streak inline -->
              <div class="smgp-profile-page__streak-inline">
                <div class="smgp-profile-page__streak-row">
                  <i class="icon-zap smgp-profile-page__streak-fire" />
                  <span class="smgp-profile-page__streak-num">{{ data.streak }}</span>
                </div>
                <span class="smgp-profile-page__streak-label">{{ $t('profile.streak_days') }}</span>
              </div>
            </div>
            <ul v-if="data.recent_xp?.length" class="smgp-profile-page__xp-list">
              <li v-for="(r, i) in data.recent_xp.slice(0, 4)" :key="i" class="smgp-profile-page__xp-row">
                <i class="icon-zap smgp-profile-page__xp-icon" />
                <span class="smgp-profile-page__xp-text">{{ r.label || r.source }}</span>
                <span class="smgp-profile-page__xp-amount">+{{ r.xp_amount }}</span>
              </li>
            </ul>
          </div>

          <ProfileWeeklyChart :days="data.week_activity" />
        </div>

        <!-- Row 2: Missions + Achievements -->
        <div class="smgp-profile-page__row-2">
          <ProfileMissions
            :daily="data.daily_missions"
            :weekly="data.weekly_missions"
            @claimed="onMissionClaimed"
          />
          <ProfileAchievements
            :achievements="data.achievements"
            :unlocked="data.achievements_unlocked"
            :total="data.achievements_total"
          />
        </div>

        <!-- Leaderboard (full width) -->
        <ProfileLeaderboard :rows="data.leaderboard" />
      </template>
    </div>

    <!-- Tab: My Courses -->
    <ProfileMyCourses v-else-if="activeTab === 'courses'" />

    <!-- Tab: Grades / Certificates -->
    <ProfileGradesCerts v-else-if="activeTab === 'grades'" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { storeToRefs } from 'pinia'
import type { ClaimMissionResult } from '~/types/profile'
import { useProfileStore } from '~/stores/profile'

import ProfileHeader from '~/components/profile/ProfileHeader.vue'
import ProfileWeeklyChart from '~/components/profile/ProfileWeeklyChart.vue'
import ProfileMissions from '~/components/profile/ProfileMissions.vue'
import ProfileAchievements from '~/components/profile/ProfileAchievements.vue'
import ProfileLeaderboard from '~/components/profile/ProfileLeaderboard.vue'

definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const profileStore = useProfileStore()
const {
  profileLoading: loading,
  profileError: error,
  profileData: data,
} = storeToRefs(profileStore)

profileStore.fetchProfile()

// Level ring
const levelCircumference = 2 * Math.PI * 34
const levelDashOffset = computed(() => {
  const pct = data.value?.level_progress_pct ?? 0
  return levelCircumference - (levelCircumference * pct) / 100
})

async function onMissionClaimed(_result: ClaimMissionResult) {}

// ── Tabs ──
type TabKey = 'profile' | 'courses' | 'grades'

const tabs = computed(() => [
  { key: 'profile' as TabKey, label: t('nav.profile'), icon: 'icon-user' },
  { key: 'courses' as TabKey, label: t('nav.courses'), icon: 'icon-book-open' },
  { key: 'grades'  as TabKey, label: t('nav.grades'),  icon: 'icon-award' },
])

const validTabs: TabKey[] = ['profile', 'courses', 'grades']
const initialTab = validTabs.includes(route.query.tab as TabKey)
  ? (route.query.tab as TabKey)
  : 'profile'
const activeTab = ref<TabKey>(initialTab)

function setTab(key: TabKey) {
  activeTab.value = key
  router.replace({ query: key === 'profile' ? {} : { tab: key } })
}
</script>

<style scoped lang="scss">
.smgp-profile-page {
  width: 100%;
  margin: 0 auto;
  padding: 1.5rem 0;

  // ── Row 1: Chart (left 50%) | Level+Streak stacked (right 50%) ──
  &__row-half {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    align-items: stretch;
    @media (max-width: 768px) { grid-template-columns: 1fr; }
  }
  // ── Row 2: 2 columns ──
  &__row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    @media (max-width: 768px) { grid-template-columns: 1fr; }
  }

  // ── Card base ──
  &__card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
  }

  // ── Level+Streak card ──
  &__level {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.75rem;
  }
  &__level-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }
  &__level-head {
    display: flex;
    align-items: center;
    gap: 0.85rem;
  }
  &__streak-inline {
    text-align: center;
    padding-left: 1rem;
    border-left: 1px solid #f1f5f9;
  }
  &__streak-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
  }
  &__streak-fire {
    font-size: 1.5rem;
    color: #10b981;
  }
  &__streak-num {
    font-size: 2.25rem;
    font-weight: 800;
    color: #10b981;
    line-height: 1;
  }
  &__streak-label {
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 0.15rem;
  }
  &__level-ring-wrap {
    position: relative;
    width: 60px;
    height: 60px;
    flex-shrink: 0;
  }
  &__level-ring {
    width: 60px;
    height: 60px;
    transform: rotate(-90deg);
  }
  &__level-track { fill: none; stroke: #e2e8f0; stroke-width: 5; }
  &__level-progress {
    fill: none; stroke: #10b981; stroke-width: 5;
    stroke-linecap: round; transition: stroke-dashoffset 0.6s ease;
  }
  &__level-num {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem; font-weight: 800; color: #10b981;
  }
  &__level-info { display: flex; flex-direction: column; }
  &__level-title { font-size: 0.95rem; font-weight: 700; color: #1e293b; }
  &__level-xp { font-size: 0.75rem; color: #94a3b8; margin-top: 0.1rem; }

  // ── Recent XP (inside level card) ──
  &__xp-list {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-direction: column; gap: 0.3rem;
    border-top: 1px solid #f1f5f9; padding-top: 0.65rem;
  }
  &__xp-row {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.3rem 0.4rem; border-radius: 6px; font-size: 0.78rem;
  }
  &__xp-icon { color: #f59e0b; font-size: 0.85rem; }
  &__xp-text {
    flex: 1; color: #475569; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  &__xp-amount { font-weight: 700; color: #059669; flex-shrink: 0; }
}

// ── Top tabs ──
.smgp-profile-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid #e2e8f0;

  &__btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.6rem 1.1rem;
    border: none;
    background: none;
    font-size: 0.88rem;
    font-weight: 500;
    color: #94a3b8;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: color 0.15s, border-color 0.15s;
    i { font-size: 1rem; }
    &:hover { color: #1e293b; }
    &--active {
      color: #10b981;
      border-bottom-color: #10b981;
    }
  }
}
</style>
