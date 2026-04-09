<template>
  <div class="smgp-course-nav">
    <button
      class="smgp-course-nav__btn smgp-course-nav__prev"
      :disabled="currentIndex <= 0"
      @click="$emit('navigate', -1)"
    >
      <i class="bi bi-chevron-left" />
      <span>{{ $t('course_page.prev') }}</span>
    </button>

    <div class="smgp-course-nav__center">
      <div class="smgp-course-progress-bar">
        <div class="smgp-course-progress-bar__track">
          <div
            class="smgp-course-progress-bar__fill"
            :style="{ width: progressPercent + '%' }"
          />
          <div
            class="smgp-course-progress-bar__cursor"
            :style="{ left: progressPercent + '%' }"
          />
        </div>
      </div>
      <div v-if="hasSelection" class="smgp-course-nav__item-counter">
        {{ currentIndex + 1 }} / {{ total }}
      </div>
    </div>

    <button
      class="smgp-course-nav__btn smgp-course-nav__next"
      :disabled="currentIndex >= total - 1"
      @click="$emit('navigate', 1)"
    >
      <span>{{ $t('course_page.next') }}</span>
      <i class="bi bi-chevron-right" />
    </button>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  currentIndex: number
  total: number
  progressPercent: number
  hasSelection: boolean
}>()

defineEmits<{
  (e: 'navigate', delta: number): void
}>()
</script>
