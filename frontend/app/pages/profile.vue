<template>
  <div class="smgp-profile-page">
    <!-- Tab navigation -->
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

    <!-- Tab: profile (overview) -->
    <div v-if="activeTab === 'profile'">
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">{{ $t('app.loading') }}</span>
        </div>
      </div>

      <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

      <template v-else-if="data">
        <ProfileHeader :data="data" />

        <ProfileStats :data="data" />

        <div class="smgp-profile-page__row">
          <ProfileWeeklyChart :days="data.week_activity" />
          <ProfileMissions
            :daily="data.daily_missions"
            :weekly="data.weekly_missions"
            @claimed="onMissionClaimed"
          />
        </div>

        <ProfileAchievements
          :achievements="data.achievements"
          :unlocked="data.achievements_unlocked"
          :total="data.achievements_total"
        />

        <div class="smgp-profile-page__row">
          <ProfileLeaderboard :rows="data.leaderboard" />
          <ProfileRecentXp :entries="data.recent_xp" />
        </div>
      </template>
    </div>

    <!-- Tab: courses -->
    <ProfileMyCourses v-else-if="activeTab === 'courses'" />

    <!-- Tab: grades / certificates -->
    <ProfileGradesCerts v-else-if="activeTab === 'grades'" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useProfileApi } from '~/composables/api_calls/useProfileApi'
import type { ProfileData, ClaimMissionResult } from '~/types/profile'

import ProfileHeader from '~/components/profile/ProfileHeader.vue'
import ProfileStats from '~/components/profile/ProfileStats.vue'
import ProfileWeeklyChart from '~/components/profile/ProfileWeeklyChart.vue'
import ProfileMissions from '~/components/profile/ProfileMissions.vue'
import ProfileAchievements from '~/components/profile/ProfileAchievements.vue'
import ProfileLeaderboard from '~/components/profile/ProfileLeaderboard.vue'
import ProfileRecentXp from '~/components/profile/ProfileRecentXp.vue'

definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { getProfileData } = useProfileApi()

// ── Profile data state ──
const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<ProfileData | null>(null)

async function fetchAll() {
  loading.value = true
  error.value = null
  const result = await getProfileData()
  loading.value = false
  if (result.error) {
    error.value = result.error
    return
  }
  data.value = result.data as ProfileData
}

fetchAll()

/**
 * Triggered after a successful mission claim. We patch the in-memory
 * profile snapshot so the header XP bar / level ring animate to the new
 * value (CSS transitions on width / stroke-dashoffset already handle the
 * tween) and the claimed mission flips to the "claimed" state without
 * needing a full refetch.
 */
function onMissionClaimed(result: ClaimMissionResult) {
  if (!data.value || !result.success) return

  data.value.xp_total           = result.xp_total
  data.value.level              = result.level
  data.value.xp_into_level      = result.xp_into_level
  data.value.xp_for_next        = result.xp_for_next
  data.value.xp_to_next         = result.xp_to_next
  data.value.level_progress_pct = result.level_progress_pct

  const flip = (m: ProfileData['daily_missions'][number]) => {
    if (m.code === result.mission_code) {
      m.claimed = true
      m.claimable = false
    }
  }
  data.value.daily_missions.forEach(flip)
  data.value.weekly_missions.forEach(flip)
}

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
  margin: 2rem auto;
  padding: 0 1rem;
  max-width: 1200px;

  &__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    @media (max-width: 900px) { grid-template-columns: 1fr; }
  }
}

.smgp-profile-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  border-bottom: 2px solid #e2e8f0;
  padding-bottom: 0;

  &__btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.65rem 1.25rem;
    border: none;
    background: none;
    font-size: 0.95rem;
    font-weight: 500;
    color: #64748b;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color 0.2s, border-color 0.2s;

    i { font-size: 1.1rem; }

    &:hover {
      color: #1e293b;
    }

    &--active {
      color: #10b981;
      border-bottom-color: #10b981;
    }
  }
}
</style>
