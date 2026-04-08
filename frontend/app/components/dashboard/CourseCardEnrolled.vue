<template>
  <article class="course-card course-card--enrolled" :data-course-id="course.id">
    <NuxtLink :to="resumeLink" class="course-card__thumb">
      <img
        :src="course.image || `https://picsum.photos/seed/course${course.id}/600/340`"
        class="course-card__thumb-img"
        :alt="course.fullname"
        loading="lazy"
      >
      <div class="course-card__diffuse" />
      <div class="course-card__play">
        <i class="fa fa-play" />
      </div>
    </NuxtLink>
    <div class="course-card__body">
      <div class="course-card__text">
        <h3 class="course-card__title">
          <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
        </h3>
        <span v-if="course.shortname" class="course-card__subtitle">{{ course.shortname }}</span>
      </div>
      <div class="course-card__ring" :data-progress="course.progress || 0">
        <svg viewBox="0 0 36 36" class="course-card__ring-svg">
          <circle class="course-card__ring-bg" cx="18" cy="18" r="15.9" />
          <circle
            class="course-card__ring-fill"
            cx="18"
            cy="18"
            r="15.9"
            :stroke-dasharray="`${course.progress || 0}, 100`"
          />
        </svg>
        <span class="course-card__ring-pct">{{ course.progress || 0 }}%</span>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { DashboardCourse } from '~/types/dashboard'

const props = defineProps<{
  course: DashboardCourse
}>()

// Resume URL — jumps straight into the last viewed activity if known,
// otherwise opens the course player at its default position.
const resumeLink = computed(() =>
  props.course.lastcmid
    ? `/courses/${props.course.id}/player?cmid=${props.course.lastcmid}`
    : `/courses/${props.course.id}/player`
)
</script>
