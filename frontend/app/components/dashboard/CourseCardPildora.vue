<template>
  <article class="pildora-card" :data-course-id="course.id">
    <NuxtLink :to="`/courses/${course.id}/landing`" class="pildora-card__thumb">
      <img
        :src="course.image || `https://picsum.photos/seed/pil${course.id}/400/240`"
        class="pildora-card__img"
        :alt="course.fullname"
        loading="lazy"
      >
      <div class="pildora-card__diffuse" />
    </NuxtLink>
    <div class="pildora-card__body">
      <span class="pildora-card__badge">{{ $t('dashboard.pildoraBadge') }}</span>
      <h3 class="pildora-card__title">
        <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
      </h3>
    </div>
  </article>
</template>

<script setup lang="ts">
import type { DashboardCourse } from '~/types/dashboard'

defineProps<{
  course: DashboardCourse
}>()
</script>
