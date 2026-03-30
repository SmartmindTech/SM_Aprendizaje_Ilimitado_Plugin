<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="smgp-mycourses">

    <div class="smartmind-catalogue-header mb-4">
      <span class="smartmind-catalogue-header__eyebrow">UNLIMITED LEARNING</span>
      <h1 class="smartmind-catalogue-header__title">{{ $t('nav.courses') }}</h1>
      <p class="smartmind-catalogue-header__desc">Manage your training and continue where you left off.</p>
    </div>

    <!-- Filter tabs -->
    <div class="smgp-mycourses__filters mb-4" data-region="mycourses-filters">
      <button
        class="smartmind-badge" :class="{ 'smartmind-badge--active': filter === 'inprogress' }"
        @click="filter = 'inprogress'"
      >
        In progress
      </button>
      <button
        class="smartmind-badge" :class="{ 'smartmind-badge--active': filter === 'completed' }"
        @click="filter = 'completed'"
      >
        Completed
      </button>
      <button
        class="smartmind-badge" :class="{ 'smartmind-badge--active': filter === 'all' }"
        @click="filter = 'all'"
      >
        All
      </button>
    </div>

    <!-- Course list -->
    <div class="smgp-mycourses__list" data-region="mycourses-list">
      <div
        v-for="course in filteredCourses" :key="course.id"
        class="smgp-mycourses__row" :data-status="course.status"
      >
        <div class="smgp-mycourses__icon-area" :class="{ 'smgp-mycourses__icon-area--completed': course.status === 'completed' }">
          <img v-if="course.image" :src="course.image" :alt="course.fullname">
          <i v-else class="fa fa-graduation-cap" />
        </div>
        <div class="smgp-mycourses__info">
          <h3 class="smgp-mycourses__course-name">{{ course.fullname }}</h3>
          <span class="smgp-mycourses__meta">
            {{ course.shortname }} · {{ course.total_activities }} modules
            <template v-if="course.categoryname"> · {{ course.categoryname }}</template>
          </span>
          <div class="smgp-mycourses__progress-bar">
            <div
              class="smgp-mycourses__progress-fill"
              :class="{ 'smgp-mycourses__progress-fill--completed': course.status === 'completed' }"
              :style="{ width: course.progress + '%' }"
            />
          </div>
          <span class="smgp-mycourses__progress-text">
            {{ course.progress }}% completed · Resource {{ course.completed_activities }} of {{ course.total_activities }}
          </span>
        </div>
        <div class="smgp-mycourses__action">
          <NuxtLink
            :to="`/courses/${course.id}/landing`"
            class="smgp-mycourses__btn"
            :class="{ 'smgp-mycourses__btn--completed': course.status === 'completed' }"
          >
            {{ course.status === 'completed' ? 'Review' : 'Continue' }}
          </NuxtLink>
          <span class="smgp-mycourses__status" :class="`smgp-mycourses__status--${course.status}`">
            {{ course.status === 'completed' ? 'COMPLETED' : 'IN PROGRESS' }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="filteredCourses.length === 0" class="catalog-section__empty">
      No courses in this category.
    </div>
  </div>
</template>

<script setup lang="ts">
const { getMyCourses } = useCourseApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const filter = ref<'inprogress' | 'completed' | 'all'>('inprogress')

const filteredCourses = computed(() => {
  if (!data.value) return []
  const enrolled = data.value.enrolledcourses || []
  const completed = data.value.completedcourses || []
  if (filter.value === 'inprogress') return enrolled
  if (filter.value === 'completed') return completed
  return [...enrolled, ...completed]
})

onMounted(async () => {
  const result = await getMyCourses()
  loading.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
})
</script>
