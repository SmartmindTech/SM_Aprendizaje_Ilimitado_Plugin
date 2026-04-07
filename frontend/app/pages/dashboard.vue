<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="catalog-dashboard">

    <!-- Welcome banner (mirrors dashboard_welcome_banner.mustache) -->
    <div class="dashboard-banner">
      <div class="dashboard-banner__blob" />
      <div class="dashboard-banner__blob dashboard-banner__blob--2" />
      <div class="dashboard-banner__content">
        <h2 class="dashboard-banner__greeting">
          <span class="dashboard-banner__emoji">👋</span> {{ greeting }}
        </h2>
        <p class="dashboard-banner__streak">{{ streakMessage }} 🔥</p>
        <div class="dashboard-banner__suggestion">
          <span class="dashboard-banner__suggestion-icon">💡</span>
          <span>
            {{ $t('dashboard.suggestion') }}
            <NuxtLink to="/catalogue" class="dashboard-banner__suggestion-link">
              {{ $t('dashboard.suggestionLink') }}
            </NuxtLink>
          </span>
        </div>
        <div class="dashboard-banner__badges">
          <span class="dashboard-banner__badge dashboard-banner__badge--streak">
            🔥 {{ streakDays }} {{ $t('dashboard.days') }}
          </span>
          <span class="dashboard-banner__badge dashboard-banner__badge--xp">
            ⚡ {{ xpPoints }} XP
          </span>
          <span class="dashboard-banner__badge dashboard-banner__badge--level">
            📊 {{ $t('dashboard.level') }} {{ userLevel }}
          </span>
        </div>
      </div>
    </div>

    <!-- Quick navigation (mirrors dashboard_quick_nav.mustache) -->
    <div class="dashboard-quicknav">
      <NuxtLink to="/courses" class="dashboard-quicknav__item dashboard-quicknav__item--green">
        <span class="dashboard-quicknav__icon">📚</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.courses') }}</span>
      </NuxtLink>
      <NuxtLink to="/catalogue" class="dashboard-quicknav__item dashboard-quicknav__item--violet">
        <span class="dashboard-quicknav__icon">🔍</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.catalogue') }}</span>
      </NuxtLink>
      <NuxtLink to="/grades-certificates" class="dashboard-quicknav__item dashboard-quicknav__item--amber">
        <span class="dashboard-quicknav__icon">🏆</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.grades') }}</span>
      </NuxtLink>
      <NuxtLink to="/profile" class="dashboard-quicknav__item dashboard-quicknav__item--cyan">
        <span class="dashboard-quicknav__icon">👤</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.profile') }}</span>
      </NuxtLink>
      <a :href="calendarUrl" class="dashboard-quicknav__item dashboard-quicknav__item--pink">
        <span class="dashboard-quicknav__icon">📅</span>
        <span class="dashboard-quicknav__label">{{ $t('dashboard.calendar') }}</span>
      </a>
      <a :href="messagesUrl" class="dashboard-quicknav__item dashboard-quicknav__item--emerald">
        <span class="dashboard-quicknav__icon">💬</span>
        <span class="dashboard-quicknav__label">{{ $t('dashboard.messages') }}</span>
      </a>
    </div>

    <!-- Continue learning (enrolled courses, mirrors dashboard_enrolled_courses.mustache) -->
    <div class="catalog-section catalog-section--enrolled">
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">
            <i class="fas fa-book catalog-section__icon catalog-section__icon--green" />
            {{ $t('dashboard.continueLearning') }}
          </h5>
          <p class="catalog-section__desc">{{ $t('dashboard.continueLearningDesc') }}</p>
        </div>
        <NuxtLink to="/courses" class="catalog-section__viewall">
          {{ $t('dashboard.viewAllCourses') }} →
        </NuxtLink>
      </div>
      <div class="catalog-section__content">
        <div v-if="enrolledCourses.length" class="dashboard-course-grid">
          <article
            v-for="course in enrolledCourses"
            :key="course.id"
            class="course-card course-card--enrolled"
            :data-course-id="course.id"
          >
            <NuxtLink :to="`/courses/${course.id}/player`" class="course-card__thumb">
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
        </div>
        <div v-else class="catalog-section__empty">
          {{ $t('dashboard.noEnrolledCourses') }}
          <NuxtLink to="/catalogue">{{ $t('nav.catalogue') }}</NuxtLink>
        </div>
      </div>
    </div>

    <!-- Completed courses -->
    <div v-if="finishedCourses.length" class="catalog-section catalog-section--finished">
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">
            <i class="fas fa-trophy catalog-section__icon catalog-section__icon--amber" />
            {{ $t('dashboard.completedCourses') }}
          </h5>
        </div>
      </div>
      <div class="catalog-section__content">
        <div class="dashboard-course-grid">
          <article
            v-for="course in finishedCourses"
            :key="course.id"
            class="course-card course-card--finished"
            :data-course-id="course.id"
          >
            <NuxtLink :to="`/courses/${course.id}/landing`" class="course-card__thumb">
              <img
                :src="course.image || `https://picsum.photos/seed/course${course.id}/600/340`"
                class="course-card__thumb-img"
                :alt="course.fullname"
                loading="lazy"
              >
              <div class="course-card__diffuse" />
            </NuxtLink>
            <div class="course-card__body">
              <div class="course-card__text">
                <h3 class="course-card__title">
                  <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
                </h3>
                <span v-if="course.shortname" class="course-card__subtitle">{{ course.shortname }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- Category sections -->
    <div
      v-for="cat in categorySections"
      :key="cat.categoryid"
      class="catalog-section catalog-section--category"
    >
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">{{ cat.categoryname }}</h5>
          <p class="catalog-section__desc">{{ cat.count }} {{ $t('dashboard.coursesAvailable') }}</p>
        </div>
        <NuxtLink :to="`/catalogue?category=${cat.categoryid}`" class="catalog-section__viewall">
          {{ $t('dashboard.viewAll') }} →
        </NuxtLink>
      </div>
      <div class="catalog-section__content">
        <div class="dashboard-course-grid">
          <article
            v-for="course in cat.courses"
            :key="course.id"
            class="course-card"
            :data-course-id="course.id"
          >
            <NuxtLink :to="`/courses/${course.id}/landing`" class="course-card__thumb">
              <img
                :src="course.image || `https://picsum.photos/seed/course${course.id}/600/340`"
                class="course-card__thumb-img"
                :alt="course.fullname"
                loading="lazy"
              >
              <div class="course-card__diffuse" />
            </NuxtLink>
            <div class="course-card__body">
              <div class="course-card__text">
                <h3 class="course-card__title">
                  <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
                </h3>
                <span v-if="course.shortname" class="course-card__subtitle">{{ course.shortname }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- Recommended -->
    <div v-if="recommendedCourses.length" class="catalog-section catalog-section--recommended">
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">
            <i class="fas fa-lightbulb catalog-section__icon catalog-section__icon--violet" />
            {{ $t('dashboard.recommendedForYou') }}
          </h5>
        </div>
      </div>
      <div class="catalog-section__content">
        <div class="dashboard-course-grid">
          <article
            v-for="course in recommendedCourses"
            :key="course.id"
            class="course-card"
            :data-course-id="course.id"
          >
            <NuxtLink :to="`/courses/${course.id}/landing`" class="course-card__thumb">
              <img
                :src="course.image || `https://picsum.photos/seed/course${course.id}/600/340`"
                class="course-card__thumb-img"
                :alt="course.fullname"
                loading="lazy"
              >
              <div class="course-card__diffuse" />
            </NuxtLink>
            <div class="course-card__body">
              <div class="course-card__text">
                <h3 class="course-card__title">
                  <NuxtLink :to="`/courses/${course.id}/landing`">{{ course.fullname }}</NuxtLink>
                </h3>
                <span v-if="course.shortname" class="course-card__subtitle">{{ course.shortname }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const authStore = useAuthStore()
const { getDashboard } = useCourseApi()
const { t } = useI18n()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

// Greeting derived from current time so we don't depend on a server-side
// `greeting` field.
const greeting = computed(() => {
  const h = new Date().getHours()
  const name = authStore.fullname?.split(' ')[0] || ''
  if (h < 12) return t('dashboard.greetingMorning', { name })
  if (h < 19) return t('dashboard.greetingAfternoon', { name })
  return t('dashboard.greetingEvening', { name })
})

// Streak / XP / level fall back to placeholder values until the
// composable returns them.
const streakDays = computed(() => data.value?.streakdays ?? 0)
const xpPoints   = computed(() => data.value?.xppoints ?? 0)
const userLevel  = computed(() => data.value?.userlevel ?? 1)

const streakMessage = computed(() =>
  streakDays.value > 0
    ? t('dashboard.streakActive', { days: streakDays.value })
    : t('dashboard.streakStart')
)

const enrolledCourses  = computed(() => data.value?.courses     ?? [])
const finishedCourses  = computed(() => data.value?.finished    ?? [])
const recommendedCourses = computed(() => data.value?.recommended ?? [])
const categorySections = computed(() => data.value?.categories  ?? [])

// External links — go to the Moodle backend for now.
const calendarUrl = computed(() => `${authStore.wwwroot}/calendar/view.php`)
const messagesUrl = computed(() => `${authStore.wwwroot}/message/index.php`)

getDashboard().then((result) => {
  loading.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
})
</script>
