<template>
  <div class="smgp-profile-leaderboard">
    <div class="smgp-profile-leaderboard__head">
      <h3 class="smgp-profile-leaderboard__title">{{ $t('profile.leaderboard_title') }}</h3>
      <span v-if="rows.length" class="smgp-profile-leaderboard__count">
        {{ $t('profile.leaderboard_total', { count: rows.length }) }}
      </span>
    </div>

    <ol v-if="rows.length" class="smgp-profile-leaderboard__list">
      <li
        v-for="row in rows" :key="row.userid"
        class="smgp-profile-leaderboard__row"
        :class="{ 'is-self': row.isself }"
      >
        <span class="smgp-profile-leaderboard__pos">#{{ row.position }}</span>
        <img
          :src="row.avatarurl"
          :alt="row.fullname"
          class="smgp-profile-leaderboard__avatar"
          loading="lazy"
        >
        <div class="smgp-profile-leaderboard__name">{{ row.fullname }}</div>
        <div class="smgp-profile-leaderboard__level">{{ $t('profile.level') }} {{ row.level }}</div>
        <div class="smgp-profile-leaderboard__xp">{{ row.xp_total }} XP</div>
      </li>
    </ol>

    <div v-else class="smgp-profile-leaderboard__empty">
      {{ $t('profile.leaderboard_empty') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import type { LeaderboardRow } from '~/types/profile'

defineProps<{ rows: LeaderboardRow[] }>()
</script>

<style scoped lang="scss">
.smgp-profile-leaderboard {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.25rem 1.5rem;

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
  &__count {
    font-size: 0.8rem;
    color: #64748b;
  }
  &__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  &__row {
    display: grid;
    grid-template-columns: 36px 36px 1fr auto auto;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 0.75rem;
    border-radius: 8px;
    background: #f8fafc;
    transition: background 0.15s;

    &.is-self {
      background: #d1fae5;
      font-weight: 600;
      box-shadow: inset 0 0 0 1px #6ee7b7;
    }
  }
  &__pos {
    font-weight: 700;
    color: #64748b;
    font-size: 0.85rem;
    text-align: center;
  }
  &__avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
  }
  &__name {
    color: #1e293b;
    font-size: 0.9rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  &__level {
    font-size: 0.75rem;
    color: #64748b;
  }
  &__xp {
    font-size: 0.8rem;
    font-weight: 700;
    color: #059669;
  }
  &__empty {
    padding: 1.5rem;
    text-align: center;
    color: #94a3b8;
    background: #f8fafc;
    border-radius: 10px;
    font-size: 0.85rem;
  }
}
</style>
