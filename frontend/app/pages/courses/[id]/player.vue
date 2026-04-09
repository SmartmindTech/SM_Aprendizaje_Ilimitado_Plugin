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
          <NuxtLink to="/profile" class="smgp-course-breadcrumb__link">
            {{ $t('course_page.mycourses_breadcrumb') }}
          </NuxtLink>
          <span class="smgp-course-breadcrumb__sep">/</span>
          <span class="smgp-course-breadcrumb__current">{{ data.coursename }}</span>
        </div>

        <!-- Activity content loaded via AJAX -->
        <div id="smgp-course-content-area" class="smgp-course-content">
          <div v-if="activityLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status" />
          </div>

          <!-- Inline render: chrome composed client-side from structured `inline` payload. -->
          <template v-else-if="activityRender === 'inline' && activityInline">
            <!-- mod_page: full body (Moodle-formatted user content) -->
            <div
              v-if="activityInline.kind === 'page'"
              class="smgp-activity-content smgp-activity-content--page"
              v-html="activityInline.content"
            />

            <!-- mod_book: chapter title + counter + body -->
            <div
              v-else-if="activityInline.kind === 'book'"
              class="smgp-activity-content smgp-activity-content--book"
            >
              <template v-if="activityInline.empty">
                <p>{{ $t('course_page.no_chapters') }}</p>
              </template>
              <template v-else>
                <div class="smgp-activity-content__chapter-info">
                  <strong>{{ activityInline.chapter?.title }}</strong>
                  <span class="text-muted">
                    ({{ activityInline.chapter?.current }}/{{ activityInline.chapter?.total }})
                  </span>
                </div>
                <div v-html="activityInline.content" />
              </template>
            </div>

            <!-- mod_resource: intro + file preview + (conditional) download -->
            <div
              v-else-if="activityInline.kind === 'resource'"
              class="smgp-activity-content smgp-activity-content--resource"
            >
              <div
                v-if="activityInline.intro"
                class="smgp-activity-content__intro"
                v-html="activityInline.intro"
              />

              <template v-if="activityInline.file">
                <div
                  v-if="activityInline.file.kind === 'image'"
                  class="smgp-activity-content__preview"
                >
                  <img
                    :src="activityInline.file.url"
                    :alt="activityInline.file.name"
                    class="smgp-activity-content__image"
                  >
                </div>

                <!-- PDF: native browser viewer with toolbar (fills content area). -->
                <div
                  v-else-if="activityInline.file.kind === 'pdf'"
                  class="smgp-activity-content__document"
                >
                  <iframe
                    :src="`${activityInline.file.url}#toolbar=1&navpanes=0`"
                    class="smgp-activity-content__document-frame"
                    :title="activityInline.file.name"
                  />
                </div>

                <!-- Office documents (doc/ppt/xls/...) via Google Docs Viewer. -->
                <div
                  v-else-if="activityInline.file.kind === 'document'"
                  class="smgp-activity-content__document"
                >
                  <iframe
                    :src="`https://docs.google.com/gview?url=${encodeURIComponent(activityInline.file.url)}&embedded=true`"
                    class="smgp-activity-content__document-frame"
                    :title="activityInline.file.name"
                  />
                </div>

                <div
                  v-else-if="activityInline.file.kind === 'video'"
                  class="smgp-activity-content__video-player"
                >
                  <video controls preload="metadata" class="smgp-video-player">
                    <source :src="activityInline.file.url" :type="activityInline.file.mimetype">
                    {{ $t('course_page.video_unsupported') }}
                  </video>
                </div>

                <div
                  v-else-if="activityInline.file.kind === 'audio'"
                  class="smgp-activity-content__audio-player"
                >
                  <audio controls preload="metadata" class="smgp-audio-player">
                    <source :src="activityInline.file.url" :type="activityInline.file.mimetype">
                  </audio>
                </div>

                <!-- Download button: skip for self-contained viewers (video, audio, pdf, document). -->
                <div
                  v-if="!['video', 'audio', 'pdf', 'document'].includes(activityInline.file.kind)"
                  class="smgp-activity-content__file mt-2"
                >
                  <a :href="activityInline.file.url" class="btn btn-primary btn-sm">
                    <i class="icon-download" />
                    {{ $t('course_page.download') }}
                    {{ activityInline.file.name }}
                    ({{ activityInline.file.size }})
                  </a>
                </div>
              </template>
            </div>

            <!-- mod_label: just the formatted intro -->
            <div
              v-else-if="activityInline.kind === 'label'"
              class="smgp-activity-content smgp-activity-content--label"
              v-html="activityInline.content"
            />

            <!-- Fallback: activity type the backend can't render inline -->
            <div v-else class="smgp-activity-content">
              <p>{{ $t('course_page.content_not_available') }}</p>
            </div>
          </template>

          <template v-else-if="activityRender === 'iframe' && activityIframeUrl">
            <div class="smgp-course-content__iframe-wrap">
              <iframe
                :src="activityIframeUrl"
                class="smgp-course-content__iframe"
                allow="fullscreen; autoplay; encrypted-media"
                allowfullscreen
              />
            </div>
          </template>
          <template v-else-if="activityRender === 'redirect' && currentActivity">
            <div class="smgp-course-content__redirect">
              <i class="icon-external-link" />
              <p>{{ $t('course_page.redirect_message') }}</p>
              <a :href="currentActivity.url" class="btn btn-success" target="_blank" rel="noopener">
                {{ $t('course_page.open_activity') }}
              </a>
            </div>
          </template>
          <div v-else class="smgp-course-content__placeholder">
            <i class="bi bi-collection-play" />
            <p>{{ $t('course_page.select_activity') }}</p>
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
            <span>{{ $t('course_page.prev') }}</span>
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
            <span>{{ $t('course_page.next') }}</span>
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
            {{ $t('course_page.module_content') }}
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
        :title="$t('course_page.collapse_sidebar')"
        @click="sidebarCollapsed = !sidebarCollapsed"
      >
        <i class="bi bi-chevron-right" />
      </button>
    </div>

    <!-- Floating Focus Mode Button -->
    <button
      id="smgp-course-focus-btn"
      class="smgp-course-focus-btn"
      :title="$t('course_page.focus_mode')"
      type="button"
    >
      <i class="bi bi-arrows-fullscreen" />
    </button>
  </div>
</template>

<script setup lang="ts">
// Structured payload returned by local_sm_graphics_plugin_get_activity_content
// for inline render mode. The Vue template renders all chrome from this data;
// the legacy `html` field on the response is ignored (kept on the backend
// only for the AMD frontend until that goes away).
interface InlineFile {
  url: string
  name: string
  size: string
  mimetype: string
  kind: 'image' | 'pdf' | 'document' | 'video' | 'audio' | 'other'
}
interface InlineChapter {
  title: string
  current: number
  total: number
}
interface InlineData {
  kind: 'page' | 'book' | 'resource' | 'label' | 'unsupported'
  content?: string
  intro?: string
  empty?: boolean
  chapter?: InlineChapter
  file?: InlineFile
}

const route = useRoute()
const { getCoursePageData } = useCourseApi()
const { call } = useMoodleAjax()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const selectedCmid = ref(0)
const activityInline = ref<InlineData | null>(null)
const activityIframeUrl = ref<string | null>(null)
const activityRender = ref<'inline' | 'iframe' | 'redirect' | null>(null)
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

/* Image preview (was an inline style in PHP). */
.smgp-activity-content__image {
  max-width: 100%;
  height: auto;
  border-radius: 8px;
}

.smgp-course-content__redirect {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 4rem 2rem;
  text-align: center;
  color: #6b7280;

  i {
    font-size: 3rem;
    color: #10b981;
  }

  p {
    margin: 0;
    font-size: 1rem;
  }
}
</style>
