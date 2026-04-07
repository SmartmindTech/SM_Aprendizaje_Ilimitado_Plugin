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
        <div class="catalog-stats__value">{{ data?.enrolled_count ?? 0 }}</div>
      </div>
      <div class="catalog-stats__card">
        <div class="catalog-stats__label">Completed</div>
        <div class="catalog-stats__value">{{ data?.completed_count ?? 0 }}</div>
      </div>
      <div class="catalog-stats__card">
        <div class="catalog-stats__label">Training hours</div>
        <div class="catalog-stats__value">{{ data?.training_hours ?? 0 }}</div>
      </div>
      <div class="catalog-stats__card">
        <div class="catalog-stats__label">Certificates</div>
        <div class="catalog-stats__value">{{ data?.certificates ?? 0 }}</div>
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

      <!-- Finished courses -->
      <div v-if="data?.hasfinished" class="catalog-section catalog-section--finished">
        <div class="catalog-section__header">
          <h5 class="catalog-section__title">Completed courses</h5>
        </div>
        <div class="catalog-section__content row g-3">
          <div v-for="course in data.finished" :key="course.id" class="col-12 col-sm-6 col-lg-3">
            <article class="course-card course-card--finished">
              <NuxtLink :to="`/courses/${course.id}/landing`" class="course-card__thumb">
                <img v-if="course.image" :src="course.image" class="course-card__thumb-img" :alt="course.fullname" loading="lazy">
                <div v-else class="course-card__image-placeholder"><i class="fa fa-trophy" /></div>
              </NuxtLink>
              <div class="course-card__info">
                <h3 class="course-card__title">
                  <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
                </h3>
                <span class="course-card__subtitle">{{ course.shortname }}</span>
                <div v-if="course.hasgrade" class="course-card__meta">
                  <i class="fa fa-star text-warning" /> {{ course.grade }} / {{ course.grademax }}
                </div>
                <div v-if="course.timecompleted_text" class="course-card__meta text-muted small">
                  <i class="fa fa-check" /> {{ course.timecompleted_text }}
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>

      <!-- Category sections -->
      <div v-for="cat in data?.categories || []" :key="cat.categoryid" class="catalog-section catalog-section--category">
        <div class="catalog-section__header">
          <h5 class="catalog-section__title">{{ cat.categoryname }} <small class="text-muted">({{ cat.count }})</small></h5>
          <NuxtLink :to="`/catalogue?category=${cat.categoryid}`" class="catalog-section__viewall">See all →</NuxtLink>
        </div>
        <div class="catalog-section__content row g-3">
          <div v-for="course in cat.courses" :key="course.id" class="col-12 col-sm-6 col-lg-3">
            <article class="course-card">
              <NuxtLink :to="`/courses/${course.id}/landing`" class="course-card__thumb">
                <img v-if="course.image" :src="course.image" class="course-card__thumb-img" :alt="course.fullname" loading="lazy">
                <div v-else class="course-card__image-placeholder"><i class="fa fa-book" /></div>
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
      </div>

      <!-- Recommended -->
      <div v-if="data?.hasrecommended" class="catalog-section catalog-section--recommended">
        <div class="catalog-section__header">
          <h5 class="catalog-section__title">Recommended for you</h5>
        </div>
        <div class="catalog-section__content row g-3">
          <div v-for="course in data.recommended" :key="course.id" class="col-12 col-sm-6 col-lg-3">
            <article class="course-card">
              <NuxtLink :to="`/courses/${course.id}/landing`" class="course-card__thumb">
                <img v-if="course.image" :src="course.image" class="course-card__thumb-img" :alt="course.fullname" loading="lazy">
                <div v-else class="course-card__image-placeholder"><i class="fa fa-lightbulb" /></div>
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

getDashboard().then((result) => {
  loading.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
})
</script>
