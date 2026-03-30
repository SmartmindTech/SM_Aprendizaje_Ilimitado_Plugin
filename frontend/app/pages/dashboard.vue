<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="catalog-dashboard">

    <div class="catalog-dashboard__header">
      <h4 class="catalog-dashboard__title">Welcome back, {{ authStore.fullname }}!</h4>
      <p class="catalog-dashboard__subtitle">
        You have {{ data?.courses?.length || 0 }} courses in progress. Keep learning where you left off.
      </p>
    </div>

    <!-- Stats row -->
    <div class="catalog-stats">
      <div class="catalog-stats__card">
        <div class="catalog-stats__label">Enrolled courses</div>
        <div class="catalog-stats__value">{{ data?.courses?.length || 0 }}</div>
      </div>
      <div class="catalog-stats__card">
        <div class="catalog-stats__label">Completed</div>
        <div class="catalog-stats__value">{{ completedCount }}</div>
      </div>
      <div class="catalog-stats__card">
        <div class="catalog-stats__label">Training hours</div>
        <div class="catalog-stats__value">—</div>
      </div>
      <div class="catalog-stats__card">
        <div class="catalog-stats__label">Certificates</div>
        <div class="catalog-stats__value">0</div>
      </div>
    </div>

    <!-- Sections -->
    <div class="catalog-dashboard__sections">

      <!-- Continue where you left off -->
      <div v-if="data?.hascourses" class="catalog-section catalog-section--enrolled">
        <div class="catalog-section__header">
          <h5 class="catalog-section__title">Continue where you left off</h5>
          <NuxtLink to="/courses" class="catalog-section__viewall">See all my courses →</NuxtLink>
        </div>
        <div class="catalog-section__content">
          <div class="smartmind-scroll smartmind-scroll--horizontal" data-region="smartmind-scroll">
            <button class="smartmind-scroll__btn smartmind-scroll__btn--prev" data-action="scroll-prev" aria-label="Previous">
              <i class="fa fa-chevron-left" />
            </button>
            <div class="smartmind-scroll__track" data-region="scroll-track">
              <div v-for="course in data.courses" :key="course.id" class="smartmind-scroll__item">
                <article class="course-card course-card--enrolled" :data-course-id="course.id">
                  <NuxtLink :to="`/courses/${course.id}/player`" class="course-card__thumb">
                    <img v-if="course.image" :src="course.image" class="course-card__thumb-img" :alt="course.fullname" loading="lazy">
                    <div v-else class="course-card__image-placeholder">
                      <i class="fa fa-graduation-cap" />
                    </div>

                    <div class="course-card__overlay course-card__overlay--resume">
                      <span class="course-card__play-btn">
                        <i class="fa fa-play" />
                      </span>
                      <div class="course-card__overlay-progress">
                        <div class="course-card__overlay-bar">
                          <div class="course-card__overlay-fill" :style="{ width: course.progress + '%' }" />
                        </div>
                        <span class="course-card__overlay-pct">{{ course.progress }}%</span>
                      </div>
                    </div>

                    <div class="course-card__progress">
                      <div class="course-card__progress-fill" :style="{ width: course.progress + '%' }" />
                    </div>
                  </NuxtLink>

                  <div class="course-card__info">
                    <h3 class="course-card__title">
                      <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
                    </h3>
                    <span class="course-card__subtitle">{{ course.shortname }}</span>
                  </div>
                </article>
              </div>
            </div>
            <button class="smartmind-scroll__btn smartmind-scroll__btn--next" data-action="scroll-next" aria-label="Next">
              <i class="fa fa-chevron-right" />
            </button>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="!data?.hascourses" class="catalog-section__empty">
        No enrolled courses yet. Browse the <NuxtLink to="/catalogue">catalogue</NuxtLink> to get started.
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore()
const { getDashboard } = useCourseApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

const completedCount = computed(() => {
  if (!data.value?.courses) return 0
  return data.value.courses.filter((c: any) => c.progress >= 100).length
})

onMounted(async () => {
  const result = await getDashboard()
  loading.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
})
</script>
