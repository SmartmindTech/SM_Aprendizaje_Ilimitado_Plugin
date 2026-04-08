<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="catalog-dashboard">

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- WELCOME BANNER  (mirrors dashboard_welcome_banner.mustache)  -->
    <!-- ════════════════════════════════════════════════════════════ -->
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

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- QUICK NAVIGATION  (mirrors dashboard_quick_nav.mustache)     -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="dashboard-quicknav">
      <NuxtLink to="/courses" class="dashboard-quicknav__item dashboard-quicknav__item--green">
        <span class="dashboard-quicknav__icon">📚</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.courses') }}</span>
      </NuxtLink>
      <NuxtLink to="/catalogue" class="dashboard-quicknav__item dashboard-quicknav__item--purple">
        <span class="dashboard-quicknav__icon">🔍</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.catalogue') }}</span>
      </NuxtLink>
      <NuxtLink to="/grades-certificates" class="dashboard-quicknav__item dashboard-quicknav__item--orange">
        <span class="dashboard-quicknav__icon">🏆</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.grades') }}</span>
      </NuxtLink>
      <NuxtLink to="/profile" class="dashboard-quicknav__item dashboard-quicknav__item--teal">
        <span class="dashboard-quicknav__icon">👤</span>
        <span class="dashboard-quicknav__label">{{ $t('nav.profile') }}</span>
      </NuxtLink>
      <a :href="calendarUrl" class="dashboard-quicknav__item dashboard-quicknav__item--blue">
        <span class="dashboard-quicknav__icon">📅</span>
        <span class="dashboard-quicknav__label">{{ $t('dashboard.calendar') }}</span>
      </a>
      <a :href="messagesUrl" class="dashboard-quicknav__item dashboard-quicknav__item--red">
        <span class="dashboard-quicknav__icon">💬</span>
        <span class="dashboard-quicknav__label">{{ $t('dashboard.messages') }}</span>
      </a>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- SEGUIR APRENDIENDO — enrolled courses                        -->
    <!-- ════════════════════════════════════════════════════════════ -->
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
          {{ $t('dashboard.continueLearningCta') }}
        </NuxtLink>
      </div>
      <div class="catalog-section__content">
        <div v-if="enrolledCourses.length" class="dashboard-course-grid">
          <DashboardCourseCardEnrolled
            v-for="course in enrolledCourses"
            :key="course.id"
            :course="course"
          />
        </div>
        <div v-else class="catalog-section__empty">
          <i class="fa fa-book" />
          <p>{{ $t('dashboard.enrolledEmpty') }}</p>
          <NuxtLink to="/catalogue" class="catalog-section__empty-link">
            {{ $t('nav.catalogue') }}
          </NuxtLink>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- VISTOS RECIENTEMENTE — courses browsed but not enrolled      -->
    <!-- (backed by get_browsed_courses external)                     -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="catalog-section catalog-section--recently-viewed">
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">
            <i class="fa fa-eye catalog-section__icon catalog-section__icon--green" />
            {{ $t('dashboard.recentlyViewed') }}
          </h5>
          <p class="catalog-section__desc">{{ $t('dashboard.recentlyViewedDesc') }}</p>
        </div>
        <NuxtLink
          v-if="recentlyViewed.length"
          to="/catalogue"
          class="catalog-section__viewall"
        >
          {{ $t('dashboard.viewAll') }} →
        </NuxtLink>
      </div>
      <div class="catalog-section__content">
        <div v-if="recentlyViewed.length" class="dashboard-recommended-grid">
          <DashboardCourseCardBasic
            v-for="course in recentlyViewed"
            :key="course.id"
            :course="course"
          />
        </div>
        <div v-else class="catalog-section__empty">
          <i class="fa fa-eye" />
          <p>{{ $t('dashboard.recentlyViewedEmpty') }}</p>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- SIGUE AVANZANDO — recommended based on completed             -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="catalog-section catalog-section--rec-completed">
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">
            <i class="fa fa-trophy catalog-section__icon catalog-section__icon--green" />
            {{ $t('dashboard.recCompleted') }}
          </h5>
          <p class="catalog-section__desc">{{ $t('dashboard.recCompletedDesc') }}</p>
        </div>
        <NuxtLink
          v-if="recommendedCourses.length"
          to="/catalogue"
          class="catalog-section__viewall"
        >
          {{ $t('dashboard.viewAll') }} →
        </NuxtLink>
      </div>
      <div class="catalog-section__content">
        <div v-if="recommendedCourses.length" class="dashboard-recommended-grid">
          <DashboardCourseCardBasic
            v-for="course in recommendedCourses"
            :key="course.id"
            :course="course"
          />
        </div>
        <div v-else class="catalog-section__empty">
          <i class="fa fa-trophy" />
          <p>{{ $t('dashboard.recCompletedEmpty') }}</p>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- PÍLDORAS — category sections                                 -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="catalog-section catalog-section--pildoras-wrap">
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">
            <i class="fas fa-bolt catalog-section__icon catalog-section__icon--green" />
            {{ $t('dashboard.pildoras') }}
          </h5>
          <p class="catalog-section__desc">{{ $t('dashboard.pildorasDesc') }}</p>
        </div>
      </div>

      <div v-if="categorySections.length" class="pildora-block">
        <div
          v-for="cat in categorySections"
          :key="cat.categoryid"
          class="pildora-subsection"
        >
          <div class="pildora-subsection__header">
            <span class="pildora-subsection__title">{{ cat.categoryname }}</span>
            <NuxtLink
              :to="`/catalogue?category=${cat.categoryid}`"
              class="pildora-subsection__viewall"
            >
              {{ $t('dashboard.viewAll') }}
            </NuxtLink>
          </div>
          <div class="pildora-grid">
            <DashboardCourseCardPildora
              v-for="course in cat.courses"
              :key="course.id"
              :course="course"
            />
          </div>
        </div>
      </div>
      <div v-else class="catalog-section__empty">
        <i class="fas fa-bolt" />
        <p>{{ $t('dashboard.pildorasEmpty') }}</p>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════ -->
    <!-- CURSOS COMPLETADOS — finished courses                        -->
    <!-- ════════════════════════════════════════════════════════════ -->
    <div class="catalog-section catalog-section--completed">
      <div class="catalog-section__header">
        <div>
          <h5 class="catalog-section__title">
            <i class="fas fa-trophy catalog-section__icon catalog-section__icon--green" />
            {{ $t('dashboard.completedCourses') }}
          </h5>
        </div>
      </div>
      <div class="catalog-section__content">
        <div v-if="finishedCourses.length" class="dashboard-recommended-grid">
          <DashboardCourseCardBasic
            v-for="course in finishedCourses"
            :key="course.id"
            :course="course"
          />
        </div>
        <div v-else class="catalog-section__empty">
          <i class="fas fa-trophy" />
          <p>{{ $t('dashboard.completedEmpty') }}</p>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DashboardCourse, CategorySection, DashboardData } from '~/types/dashboard'

const authStore = useAuthStore()
const { getDashboard, getRecentlyViewed } = useDashboardApi()
const { t } = useI18n()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<DashboardData | null>(null)
const recentlyViewed = ref<DashboardCourse[]>([])

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

const enrolledCourses    = computed<DashboardCourse[]>(() => data.value?.courses     ?? [])
const finishedCourses    = computed<DashboardCourse[]>(() => data.value?.finished    ?? [])
const recommendedCourses = computed<DashboardCourse[]>(() => data.value?.recommended ?? [])

// The backend may return up to 6 píldora categories — pick 3 at random,
// mirroring the original PHP `array_rand` behaviour in the mustache version.
// Fisher–Yates shuffle on a copy so we don't mutate the source array.
const categorySections = computed<CategorySection[]>(() => {
  const all = data.value?.categories ?? []
  if (all.length <= 3) return all
  const idx = [...all.keys()]
  for (let i = idx.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[idx[i], idx[j]] = [idx[j]!, idx[i]!]
  }
  return idx.slice(0, 3).map(i => all[i]!)
})

// External links — go to the Moodle backend for now.
const calendarUrl = computed(() => `${authStore.wwwroot}/calendar/view.php`)
const messagesUrl = computed(() => `${authStore.wwwroot}/message/index.php`)

// Fire both fetches in parallel — they don't depend on each other and
// the dashboard waits on whichever finishes last to flip `loading` off.
Promise.all([getDashboard(), getRecentlyViewed(4)]).then(([dashResult, viewedResult]) => {
  loading.value = false
  if (dashResult.error) {
    error.value = dashResult.error
  } else {
    data.value = dashResult.data as DashboardData
  }
  // Recently viewed is best-effort — surface a console warning but don't
  // block the whole dashboard if just this one call fails.
  if (viewedResult.error) {
    console.warn('[dashboard] getRecentlyViewed failed:', viewedResult.error)
  } else {
    recentlyViewed.value = (viewedResult.data as DashboardCourse[]) ?? []
  }
})
</script>
