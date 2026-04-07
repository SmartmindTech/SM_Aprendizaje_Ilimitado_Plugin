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
    :data-courseid="data.courseid"
    :data-canpost="data.canpost ? '1' : '0'"
    :data-canedit="data.isteacher ? '1' : '0'"
    :data-candeleteany="data.candeleteany ? '1' : '0'"
    :data-userid="data.userid"
    :data-userfullname="data.userfullname"
  >
    <!-- Main Body (Content + Sidebar) -->
    <div class="smgp-course-body">
      <!-- Main Content Area -->
      <div class="smgp-course-main">
        <!-- Breadcrumb -->
        <div id="smgp-course-breadcrumb" class="smgp-course-breadcrumb">
          <NuxtLink to="/courses" class="smgp-course-breadcrumb__link">
            {{ $t('course_page.mycourses_breadcrumb') || 'My courses' }}
          </NuxtLink>
          <span class="smgp-course-breadcrumb__sep">/</span>
          <span class="smgp-course-breadcrumb__current">{{ data.coursename }}</span>
        </div>

        <!-- Activity content loaded via AJAX -->
        <div id="smgp-course-content-area" class="smgp-course-content">
          <div v-if="activityLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status" />
          </div>
          <div v-else-if="activityContent" v-html="activityContent" />
          <div v-else class="smgp-course-content__placeholder">
            <i class="bi bi-collection-play" />
            <p>{{ $t('course_page.select_activity') || 'Select an activity to begin' }}</p>
          </div>
        </div>

        <!-- Activity Navigation -->
        <div class="smgp-course-nav">
          <button
            id="smgp-nav-prev"
            class="smgp-course-nav__btn smgp-course-nav__prev"
            :disabled="currentActivityIndex <= 0"
            @click="navigateActivity(-1)"
          >
            <i class="bi bi-chevron-left" />
            <span>{{ $t('course_page.prev') || 'Previous' }}</span>
          </button>
          <div class="smgp-course-nav__center">
            <div id="smgp-course-progress-bar" class="smgp-course-progress-bar">
              <div class="smgp-course-progress-bar__track">
                <div
                  id="smgp-progress-fill"
                  class="smgp-course-progress-bar__fill"
                  :style="{ width: progressPercent + '%' }"
                />
                <div
                  id="smgp-progress-cursor"
                  class="smgp-course-progress-bar__cursor"
                  :style="{ left: progressPercent + '%' }"
                />
              </div>
            </div>
            <div
              v-if="selectedCmid"
              id="smgp-activity-item-counter"
              class="smgp-course-nav__item-counter"
            >
              {{ currentActivityIndex + 1 }} / {{ flatActivities.length }}
            </div>
          </div>
          <button
            id="smgp-nav-next"
            class="smgp-course-nav__btn smgp-course-nav__next"
            :disabled="currentActivityIndex >= flatActivities.length - 1"
            @click="navigateActivity(1)"
          >
            <span>{{ $t('course_page.next') || 'Next' }}</span>
            <i class="bi bi-chevron-right" />
          </button>
        </div>

        <!-- Activity Info (below navigation bar) -->
        <div
          v-if="currentActivity"
          id="smgp-course-activity-info"
          class="smgp-course-activity-info"
        >
          <h2 id="smgp-activity-info-title" class="smgp-course-activity-info__title">
            {{ currentActivity.name }}
          </h2>
          <p id="smgp-activity-info-meta" class="smgp-course-activity-info__meta">
            {{ currentActivity.sectionname }}
          </p>
        </div>

        <!-- Tabs Section -->
        <div class="smgp-course-tabs">
          <CommentList
            v-if="data.canpost || data.candeleteany"
            :courseid="data.courseid"
            :cmid="selectedCmid"
            :can-post="data.canpost"
            :can-delete-any="data.candeleteany"
            :current-user-id="data.userid"
            :current-user-fullname="data.userfullname"
          />
        </div>
      </div>

      <!-- Module Content Sidebar (flat list) -->
      <aside id="smgp-course-sidebar" class="smgp-course-sidebar" :class="{ 'smgp-course-sidebar--collapsed': sidebarCollapsed }">
        <div class="smgp-course-sidebar__header">
          <span class="smgp-course-sidebar__title">
            {{ $t('course_page.module_content') || 'Module content' }}
          </span>
          <span class="smgp-course-sidebar__count">
            <span id="smgp-sidebar-completed">{{ data.completedactivities }}</span>/{{ data.totalactivities }}
          </span>
        </div>

        <div id="smgp-sidebar-list" class="smgp-course-sidebar__list">
          <template v-for="section in data.sections" :key="section.id">
            <template v-for="activity in section.activities" :key="activity.cmid">
              <div
                v-if="!activity.isforum && !activity.islabel"
                class="smgp-course-activity"
                :class="{
                  'smgp-course-activity--complete': activity.iscomplete,
                  'smgp-course-activity--active': selectedCmid === activity.cmid,
                }"
                :data-cmid="activity.cmid"
                :data-url="activity.url"
                :data-modname="activity.modname"
                :data-name="activity.name"
                :data-iconclass="activity.iconclass"
                :data-index="activity.index"
                :data-sectionname="activity.sectionname"
                :data-sectionindex="activity.sectionindex"
                :data-resourceindex="activity.resourceindex"
                :data-sectiontotalcount="activity.sectiontotalcount"
                @click="selectActivity(activity)"
              >
                <div class="smgp-course-activity__icon-box">
                  <i :class="activity.iconclass" />
                </div>
                <div class="smgp-course-activity__text">
                  <p class="smgp-course-activity__name">{{ activity.name }}</p>
                  <span class="smgp-course-activity__duration" :data-cmid="activity.cmid" />
                </div>
                <div class="smgp-course-activity__completion">
                  <i
                    v-if="activity.iscomplete"
                    class="bi bi-check-circle-fill smgp-course-activity__check"
                  />
                  <svg v-else class="smgp-activity-ring" viewBox="0 0 20 20">
                    <circle
                      class="smgp-activity-ring__bg"
                      cx="10" cy="10" r="7"
                      fill="none" stroke-width="2"
                    />
                    <circle
                      class="smgp-activity-ring__fill"
                      cx="10" cy="10" r="7"
                      fill="none" stroke-width="2"
                      stroke-dasharray="43.98" stroke-dashoffset="43.98"
                    />
                  </svg>
                </div>
              </div>
            </template>
          </template>
        </div>
      </aside>

      <!-- Sidebar Toggle -->
      <button
        id="smgp-sidebar-toggle"
        class="smgp-course-sidebar__toggle"
        :title="$t('course_page.collapse_sidebar') || 'Toggle sidebar'"
        @click="sidebarCollapsed = !sidebarCollapsed"
      >
        <i class="bi bi-chevron-right" />
      </button>
    </div>

    <!-- Floating Focus Mode Button -->
    <button
      id="smgp-course-focus-btn"
      class="smgp-course-focus-btn"
      :title="$t('course_page.focus_mode') || 'Focus mode'"
      type="button"
    >
      <i class="bi bi-arrows-fullscreen" />
    </button>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const { getCoursePageData } = useCourseApi()
const { call } = useMoodleAjax()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const selectedCmid = ref(0)
const activityContent = ref<string | null>(null)
const activityLoading = ref(false)
const sidebarCollapsed = ref(false)

const courseid = computed(() => Number(route.params.id))

// Build flat activity list (excluding forums and labels)
const flatActivities = computed(() => {
  if (!data.value?.sections) return []
  const activities: any[] = []
  for (const section of data.value.sections) {
    for (const activity of section.activities || []) {
      if (!activity.isforum && !activity.islabel) {
        activities.push(activity)
      }
    }
  }
  return activities
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
  const result = await call('local_sm_graphics_plugin_get_activity_content', { cmid: activity.cmid })
  activityLoading.value = false
  if (!result.error) {
    activityContent.value = (result.data as any)?.content ?? null
  }
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
    // Auto-select first activity
    if (flatActivities.value.length > 0) {
      selectActivity(flatActivities.value[0])
    }
  }
})
</script>

<style scoped>
.smgp-course-activity--active {
  background: var(--bs-primary-bg-subtle, #e8f0fe);
}
</style>
