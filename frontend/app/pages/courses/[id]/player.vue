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
        :active-completed="activityCompletedItems"
        :active-total="activityTotalItems"
        :progress-map="activityProgressMap"
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

// ── Granular intra-activity progress ─────────────────────────────────
const activityCompletedItems = ref(0)
const activityTotalItems = ref(0)
let progressPollTimer: ReturnType<typeof setInterval> | null = null

// Per-activity progress map — persists across activity switches so the
// sidebar ring for a previously-visited activity stays filled.
const activityProgressMap = ref<Map<number, { completed: number; total: number }>>(new Map())

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
  const total = flatActivities.value.length
  if (total === 0) return 0
  // Course-level only: completed activities / total.
  // Intra-activity granularity is shown by the sidebar ring, not this bar.
  const completedCount = data.value?.completedactivities ?? 0
  return Math.round((completedCount / total) * 100)
})

// ── Polling: refresh progress every 10s while an activity is open ────
function startProgressPoll(cmid: number) {
  stopProgressPoll()
  progressPollTimer = setInterval(async () => {
    const r = await call<{ cmid: number; completeditems: number; totalitems: number; iscomplete: boolean }>(
      'local_sm_graphics_plugin_get_activity_progress',
      { cmid },
    )
    if (!r.error && r.data && r.data.cmid === selectedCmid.value) {
      // Only update sub-item counts — never decrease (user may have
      // gone back to review an earlier slide).
      if (r.data.completeditems > activityCompletedItems.value) {
        activityCompletedItems.value = r.data.completeditems
      }
      if (r.data.totalitems > 0) {
        activityTotalItems.value = r.data.totalitems
      }
      // Mark complete only when the backend confirms full completion.
      if (r.data.iscomplete) {
        markActivityComplete(cmid)
      }
    }
  }, 10_000)
}
function stopProgressPoll() {
  if (progressPollTimer) {
    clearInterval(progressPollTimer)
    progressPollTimer = null
  }
}

// ── PostMessage listener for SCORM real-time updates ─────────────────
function onScormMessage(e: MessageEvent) {
  if (!e.data || e.data.type !== 'scorm-progress') return
  // Accept if cmid matches OR if cmid is 0 (shim couldn't resolve it).
  if (e.data.cmid && e.data.cmid !== selectedCmid.value) return
  const slide = e.data.currentSlide ?? e.data.furthestSlide ?? 0
  const total = e.data.totalSlides ?? 0
  // Always take the higher value — never go backwards.
  if (slide > activityCompletedItems.value) {
    activityCompletedItems.value = slide
  }
  if (total > 0) {
    activityTotalItems.value = total
  }
  // Persist in the per-activity map so the ring stays when switching.
  if (activityTotalItems.value > 0) {
    activityProgressMap.value.set(selectedCmid.value, {
      completed: activityCompletedItems.value,
      total: activityTotalItems.value,
    })
  }
  // Mark complete when:
  // 1. SCORM reports lesson_status = completed/passed, OR
  // 2. Furthest slide reached equals total slides (some packages
  //    like iSpring don't set lesson_status until the user clicks
  //    a specific button, but reaching the last slide = done).
  const status = e.data.lessonStatus ?? ''
  const furthest = e.data.furthestSlide ?? slide
  if (status === 'completed' || status === 'passed') {
    markActivityComplete(selectedCmid.value)
  } else if (activityTotalItems.value > 1 && furthest >= activityTotalItems.value) {
    markActivityComplete(selectedCmid.value)
  }
}
if (typeof window !== 'undefined') {
  window.addEventListener('message', onScormMessage)
  onUnmounted(() => {
    window.removeEventListener('message', onScormMessage)
    stopProgressPoll()
  })
}

// ── Mark activity complete + recalculate section progress ────────────
function markActivityComplete(cmid: number) {
  if (!data.value?.sections) return
  for (const section of data.value.sections) {
    for (const act of section.activities || []) {
      if (act.cmid === cmid && !act.iscomplete) {
        act.iscomplete = true
        data.value.completedactivities = (data.value.completedactivities || 0) + 1
        const visible = (section.activities || []).filter((a: any) => !a.isforum && !a.islabel)
        const done = visible.filter((a: any) => a.iscomplete).length
        section.section_progress = visible.length > 0 ? Math.round((done / visible.length) * 100) : 0
        return
      }
    }
  }
}

const selectActivity = async (activity: any) => {
  if (activity.islabel) return

  // Save current activity's progress before switching.
  if (selectedCmid.value && activityTotalItems.value > 0) {
    activityProgressMap.value.set(selectedCmid.value, {
      completed: activityCompletedItems.value,
      total: activityTotalItems.value,
    })
  }

  // Stop polling for the previous activity.
  stopProgressPoll()

  selectedCmid.value = activity.cmid
  activityLoading.value = true
  activityInline.value = null
  activityIframeUrl.value = null
  activityRender.value = null

  // Restore saved progress for this activity (if revisiting).
  const saved = activityProgressMap.value.get(activity.cmid)
  activityCompletedItems.value = saved?.completed ?? 0
  activityTotalItems.value = saved?.total ?? 0

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

  // Capture initial intra-activity progress.
  activityCompletedItems.value = (payload?.completeditems as number) ?? 0
  activityTotalItems.value = (payload?.totalpages as number) ?? 0

  // Start polling for progress updates while the activity is open.
  startProgressPoll(activity.cmid)
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
