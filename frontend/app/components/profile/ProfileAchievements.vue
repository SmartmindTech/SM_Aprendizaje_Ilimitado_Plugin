<template>
  <div class="smgp-profile-achievements">
    <div class="smgp-profile-achievements__head">
      <h3 class="smgp-profile-achievements__title">{{ $t('profile.achievements') }}</h3>
      <span class="smgp-profile-achievements__meta">
        {{ $t('profile.achievements_unlocked', { unlocked, total }) }}
      </span>
    </div>
    <div class="smgp-profile-achievements__grid">
      <div
        v-for="ach in achievements" :key="ach.code"
        class="smgp-achievement"
        :class="{ 'smgp-achievement--locked': !ach.unlocked }"
        :title="ach.description"
      >
        <div class="smgp-achievement__icon">
          <i :class="iconClass(ach.icon)" />
        </div>
        <div class="smgp-achievement__body">
          <div class="smgp-achievement__name">{{ ach.name }}</div>
          <div class="smgp-achievement__desc">{{ ach.description }}</div>
          <div v-if="!ach.unlocked" class="smgp-achievement__progress">
            <div class="smgp-achievement__progress-bar">
              <div
                class="smgp-achievement__progress-fill"
                :style="{ width: ach.progress_pct + '%' }"
              />
            </div>
            <span class="smgp-achievement__progress-text">
              {{ ach.current_value }} / {{ ach.condition_value }}
            </span>
          </div>
          <div v-else class="smgp-achievement__reward">+{{ ach.xp_reward }} XP</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ProfileAchievement } from '~/types/profile'

defineProps<{
  achievements: ProfileAchievement[]
  unlocked: number
  total: number
}>()

// Maps the catalog `icon` field (stable identifier stored in the DB) to the
// Lucide icon class loaded by theme_smartmind/scss/bootstrapicons.scss.
function iconClass(icon: string): string {
  const map: Record<string, string> = {
    play:           'icon-play',
    graduation:     'icon-graduation-cap',
    'check-circle': 'icon-check-circle',
    book:           'icon-book-open',
    fire:           'icon-zap',
    clock:          'icon-clock',
    star:           'icon-star',
    trophy:         'icon-trophy',
    bolt:           'icon-zap',
  }
  return map[icon] || 'icon-trophy'
}
</script>

<style scoped lang="scss">
.smgp-profile-achievements {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.5rem;
  margin-bottom: 1.5rem;

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
  &__meta {
    font-size: 0.85rem;
    color: #64748b;
  }
  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.85rem;
  }
}

.smgp-achievement {
  display: flex;
  gap: 0.85rem;
  padding: 0.85rem;
  border-radius: 10px;
  background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
  border: 1px solid #d1fae5;
  transition: transform 0.15s;

  &:hover { transform: translateY(-2px); }

  &--locked {
    background: #f8fafc;
    border-color: #e2e8f0;
    opacity: 0.75;

    .smgp-achievement__icon {
      background: #e2e8f0;
      color: #94a3b8;
    }
  }

  &__icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #10b981;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
  }
  &__body {
    flex: 1;
    min-width: 0;
  }
  &__name {
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
  }
  &__desc {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.15rem;
    line-height: 1.3;
  }
  &__progress {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.4rem;
  }
  &__progress-bar {
    flex: 1;
    height: 5px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
  }
  &__progress-fill {
    height: 100%;
    background: #10b981;
    border-radius: 999px;
  }
  &__progress-text {
    font-size: 0.7rem;
    color: #94a3b8;
    white-space: nowrap;
  }
  &__reward {
    margin-top: 0.3rem;
    font-size: 0.7rem;
    font-weight: 700;
    color: #059669;
  }
}
</style>
