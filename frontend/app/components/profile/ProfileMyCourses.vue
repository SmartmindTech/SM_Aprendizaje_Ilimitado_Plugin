<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="smgp-mycourses">
    <!-- Filter tabs -->
    <div class="smgp-mycourses__filters mb-4" data-region="mycourses-filters">
      <button
        class="smartmind-badge" :class="{ 'smartmind-badge--active': filter === 'inprogress' }"
        @click="filter = 'inprogress'"
      >
        {{ $t('mycourses.inprogress') }}
      </button>
      <button
        class="smartmind-badge" :class="{ 'smartmind-badge--active': filter === 'completed' }"
        @click="filter = 'completed'"
      >
        {{ $t('mycourses.completed') }}
      </button>
      <button
        class="smartmind-badge" :class="{ 'smartmind-badge--active': filter === 'all' }"
        @click="filter = 'all'"
      >
        {{ $t('mycourses.all') }}
      </button>
    </div>

    <!-- Course list -->
    <div class="smgp-mycourses__list" data-region="mycourses-list">
      <NuxtLink
        v-for="course in filteredCourses" :key="course.id"
        :to="course.lastcmid ? `/courses/${course.id}/player?cmid=${course.lastcmid}` : `/courses/${course.id}/player`"
        class="smgp-mycourses__row" :data-status="course.status"
      >
        <div class="smgp-mycourses__image">
          <img v-if="course.image" :src="course.image" :alt="course.fullname" loading="lazy">
          <div v-else class="smgp-mycourses__image-placeholder"><i class="fa fa-graduation-cap" /></div>
        </div>
        <div class="smgp-mycourses__info">
          <h3 class="smgp-mycourses__course-name">{{ course.fullname }}</h3>
          <span class="smgp-mycourses__meta">
            {{ course.shortname }} · {{ course.total_activities }} {{ $t('mycourses.modules') }}
          </span>
          <div class="smgp-mycourses__progress">
            <div class="smgp-mycourses__progress-bar">
              <div
                class="smgp-mycourses__progress-fill"
                :class="{ 'smgp-mycourses__progress-fill--completed': course.status === 'completed' }"
                :style="{ width: course.progress + '%' }"
              />
            </div>
            <span class="smgp-mycourses__progress-text">{{ course.progress }}%</span>
          </div>
        </div>
        <div class="smgp-mycourses__action">
          <span
            class="smgp-mycourses__btn"
            :class="{ 'smgp-mycourses__btn--completed': course.status === 'completed' }"
          >
            {{ course.status === 'completed' ? $t('mycourses.review') : $t('mycourses.continue') }}
          </span>
        </div>
      </NuxtLink>
    </div>

    <div v-if="filteredCourses.length === 0" class="catalog-section__empty">
      {{ $t('mycourses.empty') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useProfileStore } from '~/stores/profile'

const profileStore = useProfileStore()
const {
  myCoursesLoading: loading,
  myCoursesError: error,
  myCoursesData: data,
} = storeToRefs(profileStore)

profileStore.fetchMyCourses()

const filter = ref<'inprogress' | 'completed' | 'all'>('inprogress')

const filteredCourses = computed(() => {
  if (!data.value) return []
  const enrolled = data.value.enrolledcourses || []
  const completed = data.value.completedcourses || []
  if (filter.value === 'inprogress') return enrolled
  if (filter.value === 'completed') return completed
  return [...enrolled, ...completed]
})
</script>
