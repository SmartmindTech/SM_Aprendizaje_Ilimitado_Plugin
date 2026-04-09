<template>
  <div class="smgp-profile-recent">
    <h3 class="smgp-profile-recent__title">{{ $t('profile.recent_xp_title') }}</h3>
    <ul v-if="entries.length" class="smgp-profile-recent__list">
      <li v-for="(r, i) in entries" :key="i" class="smgp-profile-recent__row">
        <i class="smgp-profile-recent__icon icon-zap" />
        <span class="smgp-profile-recent__text">{{ r.label || r.source }}</span>
        <span class="smgp-profile-recent__xp">+{{ r.xp_amount }} XP</span>
      </li>
    </ul>
    <div v-else class="smgp-profile-recent__empty">
      {{ $t('profile.recent_xp_empty') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ProfileXpEntry } from '~/types/profile'

// The backend already returns a localized `label` for every entry, so the
// SPA just renders it. The fallback to `source` covers any legacy row that
// might have been created before the label field was added.
defineProps<{ entries: ProfileXpEntry[] }>()
</script>

<style scoped lang="scss">
.smgp-profile-recent {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.25rem 1.5rem;

  &__title {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
  }
  &__list {
    list-style: none;
    margin: 1rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  }
  &__row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.65rem;
    background: #f8fafc;
    border-radius: 8px;
  }
  &__icon {
    color: #f59e0b;
  }
  &__text {
    flex: 1;
    font-size: 0.85rem;
    color: #1e293b;
  }
  &__xp {
    font-size: 0.8rem;
    font-weight: 700;
    color: #059669;
  }
  &__empty {
    margin-top: 1rem;
    padding: 1.5rem;
    text-align: center;
    color: #94a3b8;
    background: #f8fafc;
    border-radius: 10px;
    font-size: 0.9rem;
  }
}
</style>
