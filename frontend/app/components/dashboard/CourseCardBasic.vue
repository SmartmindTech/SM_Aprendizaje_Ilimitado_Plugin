<template>
  <article class="recommended-card" :data-course-id="course.id">
    <NuxtLink :to="`/courses/${course.id}/landing`" class="recommended-card__thumb">
      <img
        :src="course.image || `https://picsum.photos/seed/rec${course.id}/600/400`"
        class="recommended-card__thumb-img"
        :alt="course.fullname"
        loading="lazy"
      >
      <div class="recommended-card__diffuse" />
    </NuxtLink>
    <div class="recommended-card__info">
      <span v-if="course.sm_category" class="recommended-card__badge">{{ course.sm_category }}</span>
      <h3 class="recommended-card__title">
        <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
      </h3>
      <div v-if="course.shortname" class="recommended-card__meta">
        <span>{{ course.shortname }}</span>
      </div>
    </div>
  </article>
</template>

<script setup lang="ts">
import type { DashboardCourse } from '~/types/dashboard'

defineProps<{
  course: DashboardCourse
}>()
</script>
