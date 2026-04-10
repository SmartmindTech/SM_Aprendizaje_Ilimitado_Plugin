<template>
  <div ref="rootEl" class="smgp-achievements">
    <div class="smgp-achievements__head">
      <h3 class="smgp-achievements__title">{{ $t('profile.achievements') }}</h3>
      <span class="smgp-achievements__meta">{{ unlocked }}/{{ total }}</span>
    </div>
    <div class="smgp-achievements__grid">
      <div
        v-for="ach in sortedAchievements"
        :key="ach.code"
        class="smgp-achievements__item"
        :class="{
          'smgp-achievements__item--locked': !ach.unlocked,
          'smgp-achievements__item--selected': openCode === ach.code,
        }"
        @click="onItemClick($event, ach.code)"
      >
        <div class="smgp-achievements__icon">
          <i :class="iconClass(ach.icon)" />
        </div>
        <span class="smgp-achievements__name">{{ ach.name }}</span>
        <div v-if="!ach.unlocked" class="smgp-achievements__bar">
          <div class="smgp-achievements__bar-fill" :style="{ width: ach.progress_pct + '%' }" />
        </div>
      </div>
    </div>

    <!-- Floating popup (fixed position, outside grid flow) -->
    <Teleport to="body">
      <transition name="smgp-ach-fade">
        <div
          v-if="openAch"
          ref="popupEl"
          class="smgp-achievements__popup"
          :style="popupStyle"
          @click.stop
        >
          <div class="smgp-achievements__popup-head">
            <div class="smgp-achievements__popup-icon" :class="{ 'smgp-achievements__popup-icon--locked': !openAch.unlocked }">
              <i :class="iconClass(openAch.icon)" />
            </div>
            <div class="smgp-achievements__popup-info">
              <span class="smgp-achievements__popup-name">{{ openAch.name }}</span>
              <span v-if="openAch.unlocked" class="smgp-achievements__popup-reward">+{{ openAch.xp_reward }} XP</span>
            </div>
            <button type="button" class="smgp-achievements__popup-close" @click.stop="openCode = null">
              <i class="icon-x" />
            </button>
          </div>
          <p class="smgp-achievements__popup-desc">{{ openAch.description }}</p>
          <div v-if="!openAch.unlocked" class="smgp-achievements__popup-progress">
            <div class="smgp-achievements__popup-bar">
              <div class="smgp-achievements__popup-bar-fill" :style="{ width: openAch.progress_pct + '%' }" />
            </div>
            <span class="smgp-achievements__popup-counter">{{ openAch.current_value }} / {{ openAch.condition_value }}</span>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import type { ProfileAchievement } from '~/types/profile'

const props = defineProps<{
  achievements: ProfileAchievement[]
  unlocked: number
  total: number
}>()

const sortedAchievements = computed(() => {
  const all = [...props.achievements]
  all.sort((a, b) => {
    if (a.unlocked && !b.unlocked) return -1
    if (!a.unlocked && b.unlocked) return 1
    if (!a.unlocked && !b.unlocked) return (b.progress_pct ?? 0) - (a.progress_pct ?? 0)
    return 0
  })
  return all
})

const openCode = ref<string | null>(null)
const openAch = computed(() =>
  openCode.value ? props.achievements.find(a => a.code === openCode.value) ?? null : null
)
const popupStyle = ref<Record<string, string>>({})
const rootEl = ref<HTMLElement | null>(null)
const popupEl = ref<HTMLElement | null>(null)

function onItemClick(event: MouseEvent, code: string) {
  if (openCode.value === code) {
    openCode.value = null
    return
  }
  openCode.value = code

  // Position popup next to the clicked item
  const el = (event.currentTarget as HTMLElement)
  const rect = el.getBoundingClientRect()
  const popupW = 260
  const popupH = 160 // approximate

  // Prefer right side; if not enough space, go left
  let left = rect.right + 8
  if (left + popupW > window.innerWidth - 16) {
    left = rect.left - popupW - 8
  }
  // If still off-screen (very narrow), center below
  if (left < 8) {
    left = rect.left + rect.width / 2 - popupW / 2
  }

  // Vertical: align top with the item; clamp to viewport
  let top = rect.top
  if (top + popupH > window.innerHeight - 16) {
    top = window.innerHeight - popupH - 16
  }
  if (top < 8) top = 8

  popupStyle.value = {
    top: `${top}px`,
    left: `${left}px`,
  }
}

function handleClickOutside(e: MouseEvent) {
  if (!openCode.value) return
  const target = e.target as Node
  if (rootEl.value && rootEl.value.contains(target)) return
  if (popupEl.value && popupEl.value.contains(target)) return
  openCode.value = null
}
function handleEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') openCode.value = null
}
onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  document.addEventListener('keydown', handleEsc)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  document.removeEventListener('keydown', handleEsc)
  openCode.value = null
})

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

<style lang="scss">
.smgp-achievements {
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
  padding: 1.25rem;

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
  }
  &__title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
  }
  &__meta {
    font-size: 0.75rem;
    color: #94a3b8;
  }
  &__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.6rem;
  }
  &__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 0.7rem 0.3rem;
    border-radius: 12px;
    cursor: pointer;
    transition: transform 0.15s, background 0.15s;

    &:hover { transform: translateY(-2px); background: #f8fafc; }
    &--selected { background: #ecfdf5; }

    &--locked {
      opacity: 0.5;
      .smgp-achievements__icon {
        background: #e2e8f0;
        color: #94a3b8;
      }
      &.smgp-achievements__item--selected { opacity: 0.8; }
    }
  }
  &__icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: #10b981;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    margin-bottom: 0.3rem;
  }
  &__name {
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
    line-height: 1.2;
    max-width: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  &__bar {
    width: 100%;
    max-width: 48px;
    height: 3px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    margin-top: 0.2rem;
  }
  &__bar-fill {
    height: 100%;
    background: #10b981;
    border-radius: 999px;
  }

  // ── Floating popup (teleported to body) ──
  &__popup {
    position: fixed;
    width: 260px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18);
    padding: 0.85rem;
    z-index: 1000;
    text-align: left;
    cursor: default;
  }
  &__popup-head {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin-bottom: 0.4rem;
  }
  &__popup-icon {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    background: #10b981;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
    &--locked {
      background: #e2e8f0;
      color: #94a3b8;
    }
  }
  &__popup-info {
    flex: 1;
    min-width: 0;
  }
  &__popup-name {
    display: block;
    font-size: 0.82rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
  }
  &__popup-reward {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    color: #059669;
  }
  &__popup-close {
    border: none;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    &:hover { background: #f1f5f9; color: #1e293b; }
  }
  &__popup-desc {
    font-size: 0.75rem;
    color: #64748b;
    line-height: 1.4;
    margin: 0 0 0.45rem;
  }
  &__popup-progress {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  &__popup-bar {
    flex: 1;
    height: 5px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
  }
  &__popup-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #34d399);
    border-radius: 999px;
  }
  &__popup-counter {
    font-size: 0.68rem;
    color: #94a3b8;
    white-space: nowrap;
  }
}

.smgp-ach-fade-enter-active,
.smgp-ach-fade-leave-active {
  transition: opacity 0.12s ease, transform 0.12s ease;
}
.smgp-ach-fade-enter-from,
.smgp-ach-fade-leave-to {
  opacity: 0;
}
</style>
