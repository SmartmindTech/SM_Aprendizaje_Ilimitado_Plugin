<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div
    v-else-if="data"
    class="smgp-course-page"
    :class="{
      'smgp-course-page--sidebar-collapsed': sidebarCollapsed,
      'smgp-course-page--focus': focusMode,
    }"
  >
    <div class="smgp-course-body">
      <!-- Header (breadcrumb + current activity title) — left column, row 1 -->
      <header class="smgp-course-header">
        <div class="smgp-course-breadcrumb">
          <NuxtLink to="/profile?tab=courses" class="smgp-course-breadcrumb__link">
            {{ $t('course_page.mycourses_breadcrumb') }}
          </NuxtLink>
          <span class="smgp-course-breadcrumb__sep">/</span>
          <span class="smgp-course-breadcrumb__current">{{ data.coursename }}</span>
        </div>

        <div v-if="currentActivity" class="smgp-course-activity-info">
          <h2 class="smgp-course-activity-info__title">{{ currentActivity.name }}</h2>
          <p class="smgp-course-activity-info__meta">{{ currentActivity.sectionname }}</p>
        </div>
      </header>

      <!-- Main column (content + nav + comments) — left column, row 2 -->
      <div
        class="smgp-course-main"
        :class="{ 'smgp-course-main--iframe': activityRender === 'iframe' }"
      >
        <div
          class="smgp-course-content"
          :class="{ 'smgp-course-content--iframe': activityRender === 'iframe' }"
        >
          <CoursePlayerActivity
            :loading="activityLoading"
            :render="activityRender"
            :inline="activityInline"
            :iframe-url="activityIframeUrl"
            :redirect-url="currentActivity?.url"
          />
        </div>

        <CoursePlayerNav
          :current-index="currentActivityIndex"
          :total="flatActivities.length"
          :progress-percent="progressPercent"
          :has-selection="!!selectedCmid"
          @navigate="navigateActivity"
        />

        <CommentList
          v-if="data.canpost || data.candeleteany"
          class="smgp-course-tabs"
          :courseid="data.courseid"
          :cmid="selectedCmid"
          :can-post="data.canpost"
          :can-delete-any="data.candeleteany"
          :current-user-id="data.userid"
          :current-user-fullname="data.userfullname"
        />
      </div>

      <!-- Sidebar toggle (between main and sidebar) -->
      <button
        class="smgp-course-sidebar__toggle"
        :title="$t('course_page.collapse_sidebar')"
        @click="sidebarCollapsed = !sidebarCollapsed"
      >
        <i :class="sidebarCollapsed ? 'bi bi-chevron-left' : 'bi bi-chevron-right'" />
      </button>

      <!-- Module content sidebar — right column, spans both rows -->
      <CoursePlayerSidebar
        :sections="data.sections"
        :selected-cmid="selectedCmid"
        :collapsed="sidebarCollapsed"
        :completed="data.completedactivities"
        :total="data.totalactivities"
        @select="selectActivity"
      />
    </div>

    <!-- Floating focus mode button -->
    <button
      class="smgp-course-focus-btn"
      :title="$t('course_page.focus_mode')"
      type="button"
      @click="focusMode = !focusMode"
    >
      <i :class="focusMode ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen'" />
    </button>
  </div>
</template>

<script setup lang="ts">
import type { InlineData, ActivityRender } from '~/types/coursePlayer'

const route = useRoute()
const { getCoursePageData } = useCourseApi()
const { call } = useMoodleAjax()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const selectedCmid = ref(0)
const activityInline = ref<InlineData | null>(null)
const activityIframeUrl = ref<string | null>(null)
const activityRender = ref<ActivityRender>(null)
const activityLoading = ref(false)
const sidebarCollapsed = ref(false)
const focusMode = ref(false)

watch(focusMode, (on) => {
  document.documentElement.classList.toggle('smgp-focus-active', on)
})

onBeforeUnmount(() => {
  document.documentElement.classList.remove('smgp-focus-active')
})

const courseid = computed(() => Number(route.params.id))

// Flat list of all selectable activities (forums and labels excluded).
const flatActivities = computed(() => {
  if (!data.value?.sections) return []
  const list: any[] = []
  for (const section of data.value.sections) {
    for (const activity of section.activities || []) {
      if (!activity.isforum && !activity.islabel) list.push(activity)
    }
  }
  return list
})

const currentActivityIndex = computed(() =>
  flatActivities.value.findIndex((a: any) => a.cmid === selectedCmid.value),
)

const currentActivity = computed(() =>
  flatActivities.value[currentActivityIndex.value] ?? null,
)

const progressPercent = computed(() => {
  if (!flatActivities.value.length) return 0
  return Math.round(((currentActivityIndex.value + 1) / flatActivities.value.length) * 100)
})

const selectActivity = async (activity: any) => {
  if (activity.islabel) return
  selectedCmid.value = activity.cmid
  activityLoading.value = true
  activityInline.value = null
  activityIframeUrl.value = null
  activityRender.value = null
  const result = await call('local_sm_graphics_plugin_get_activity_content', { cmid: activity.cmid })
  activityLoading.value = false
  if (result.error) {
    error.value = result.error
    return
  }
  const payload = result.data as any
  activityRender.value = payload?.rendermode ?? null
  activityInline.value = (payload?.inline as InlineData | undefined) ?? null
  activityIframeUrl.value = payload?.iframeurl ?? null
}

const navigateActivity = (delta: number) => {
  const newIndex = currentActivityIndex.value + delta
  if (newIndex >= 0 && newIndex < flatActivities.value.length) {
    selectActivity(flatActivities.value[newIndex])
  }
}

getCoursePageData(courseid.value).then((result) => {
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
    if (flatActivities.value.length > 0) {
      selectActivity(flatActivities.value[0])
    }
  }
})
</script>
