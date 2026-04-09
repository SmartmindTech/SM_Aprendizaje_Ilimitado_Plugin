<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="smgp-landing" :data-courseid="data.courseid">
    <!-- ============ HERO BANNER ============ -->
    <section
      class="smgp-landing-hero"
      :style="data.hasimage ? { backgroundImage: `url(${data.courseimageurl})` } : {}"
    >
      <div class="smgp-landing-hero__overlay">
        <div class="smgp-landing-hero__content">
          <div class="smgp-landing-hero__left">
            <button type="button" class="smgp-landing-hero__back" @click="$router.back()">
              <i class="icon-arrow-left" />
              {{ $t('landing.back') }}
            </button>
            <h1 class="smgp-landing-hero__title">{{ data.coursename }}</h1>
            <div class="smgp-landing-hero__meta">
              <span class="smgp-landing-hero__meta-item">
                <i class="icon-bar-chart-2" />
                {{ data.level_label }}
              </span>
              <span class="smgp-landing-hero__meta-item">
                <i class="icon-list-checks" />
                {{ data.section_count }} {{ $t('landing.sections') }}
              </span>
              <span class="smgp-landing-hero__meta-item">
                <i class="icon-layout-grid" />
                {{ data.total_activities }} {{ $t('landing.modules') }}
              </span>
            </div>
          </div>

          <div class="smgp-landing-hero__right">
            <div class="smgp-landing-hero__card">
              <div class="smgp-landing-hero__ring-row">
                <div class="smgp-landing-hero__ring-container">
                  <svg class="smgp-landing-hero__ring" viewBox="0 0 64 64">
                    <circle cx="32" cy="32" r="28" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="6" />
                    <circle
                      cx="32" cy="32" r="28" fill="none" stroke="#10b981" stroke-width="6"
                      stroke-linecap="round" :stroke-dasharray="ringCircumference"
                      :stroke-dashoffset="ringOffset"
                    />
                  </svg>
                </div>
                <div class="smgp-landing-hero__ring-text">
                  <span class="smgp-landing-hero__ring-pct">{{ data.progress }}%</span>
                  <span class="smgp-landing-hero__ring-label">{{ $t('landing.completed') }}</span>
                </div>
              </div>

              <div class="smgp-landing-hero__stats">
                {{ $t('landing.lessons_progress', { done: data.total_completed, total: data.total_activities }) }}
              </div>

              <NuxtLink
                v-if="data.is_enrolled_real || data.is_enrolled"
                :to="`/courses/${data.courseid}/player`"
                class="smgp-landing-hero__cta"
              >
                <i class="icon-play" />
                {{ data.has_started ? $t('landing.continue') : $t('landing.start') }}
              </NuxtLink>
              <button
                v-else
                type="button"
                class="smgp-landing-hero__cta"
                :disabled="enrolling"
                @click="enrol"
              >
                <span v-if="enrolling" class="spinner-border spinner-border-sm" />
                <i v-else class="icon-user-plus" />
                {{ $t('landing.enrol') }}
              </button>

              <div class="smgp-landing-hero__card-footer">
                <div class="smgp-landing-hero__card-info">
                  <i class="icon-award" />
                  <span>{{ $t('landing.certificate_included') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ BODY ============ -->
    <div class="smgp-landing__body-wrapper">
      <div class="smgp-landing__body">
        <!-- Left column: course content sections -->
        <div class="smgp-landing__main">
          <h2 class="smgp-landing__content-heading">{{ $t('landing.course_content') }}</h2>

          <div
            v-for="section in data.sections"
            :key="section.number"
            class="smgp-landing__section"
            :class="{ 'smgp-landing__section--expanded': expandedSections[section.number] }"
          >
            <div
              class="smgp-landing__section-header"
              role="button"
              tabindex="0"
              :aria-expanded="expandedSections[section.number] ? 'true' : 'false'"
              @click="toggleSection(section.number)"
              @keydown.enter="toggleSection(section.number)"
            >
              <div class="smgp-landing__section-left">
                <div class="smgp-landing__section-number">{{ section.section_number }}</div>
                <div class="smgp-landing__section-title">
                  <span class="smgp-landing__section-name">{{ section.name }}</span>
                  <span class="smgp-landing__section-subtitle">
                    {{ section.activity_count }} {{ $t('landing.elements') }} · {{ section.completed_count }} {{ $t('landing.completed_count') }}
                  </span>
                </div>
              </div>
              <div class="smgp-landing__section-right">
                <div class="smgp-landing__section-progress">
                  <div class="smgp-landing__section-progress-bar" :style="{ width: section.section_progress + '%' }" />
                </div>
                <span class="smgp-landing__section-pct">{{ section.section_progress }}%</span>
                <i class="icon-chevron-down smgp-landing__chevron" />
              </div>
            </div>
            <div v-if="section.hasactivities || data.canedit" class="smgp-landing__section-body">
              <ul v-if="section.hasactivities" class="smgp-landing__activities">
                <li
                  v-for="activity in section.activities"
                  :key="activity.cmid"
                  class="smgp-landing__activity"
                  :class="{ 'smgp-landing__activity--current': activity.iscurrent }"
                  :data-cmid="activity.cmid"
                >
                  <div class="smgp-landing__activity-status">
                    <i v-if="activity.iscomplete" class="icon-check-circle smgp-landing__activity-check" />
                    <i v-else-if="activity.iscurrent" class="icon-play-circle smgp-landing__activity-play" />
                    <span v-else class="smgp-landing__activity-circle" />
                  </div>
                  <div class="smgp-landing__activity-info">
                    <span class="smgp-landing__activity-name">{{ activity.name }}</span>
                    <div class="smgp-landing__activity-meta">
                      <span
                        class="smgp-landing__activity-badge"
                        :class="`smgp-landing__activity-badge--${activity.type_color || 'green'}`"
                      >
                        <i :class="activity.iconclass" />
                        {{ activity.modtypelabel }}
                      </span>
                      <span v-if="activity.has_duration" class="smgp-landing__activity-duration">
                        {{ activity.duration_minutes }} min
                      </span>
                    </div>
                  </div>
                  <button
                    v-if="data.canedit"
                    type="button"
                    class="smgp-landing__delete-btn"
                    :data-cmid="activity.cmid"
                    @click.stop="deleteState = { cmid: activity.cmid, name: activity.name }"
                  >
                    <i class="icon-circle-x" />
                  </button>
                </li>
              </ul>
              <div v-if="data.canedit" class="smgp-landing__add-section">
                <button
                  type="button"
                  class="smgp-landing__add-btn"
                  @click="togglePicker(section.number)"
                >
                  <i class="icon-plus" aria-hidden="true" />
                  {{ $t('landing.add_activity') }}
                </button>
                <AddActivityPicker
                  v-if="openPickerSection === section.number"
                  @select="onActivitySelect($event, section.number)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Right column: sidebar -->
        <aside class="smgp-landing__sidebar">
          <div v-if="data.has_content_types" class="smgp-landing__sidebar-card">
            <h3 class="smgp-landing__sidebar-title">{{ $t('landing.content_types') }}</h3>
            <ul class="smgp-landing__content-types">
              <li v-for="ct in data.content_types" :key="ct.type_label">
                <i
                  :class="[ct.type_icon, 'smgp-landing__content-type-icon', `smgp-landing__content-type-icon--${ct.type_color || 'green'}`]"
                />
                <span class="smgp-landing__content-type-label">{{ ct.type_label }}</span>
                <span class="smgp-landing__content-type-count">{{ ct.type_count }}</span>
              </li>
            </ul>
          </div>

          <div v-if="data.is_enrolled_real" class="smgp-landing__sidebar-actions">
            <button
              type="button"
              class="smgp-landing__btn smgp-landing__btn--danger"
              @click="showUnenrolModal = true"
            >
              <i class="icon-log-out" />
              {{ $t('landing.unenrol') }}
            </button>
          </div>

          <div v-if="data.canedit" class="smgp-landing__sidebar-actions" style="margin-top: 0.75rem;">
            <NuxtLink
              :to="`/courses/${courseid}/edit`"
              class="smgp-landing__btn smgp-landing__btn--secondary"
            >
              <i class="icon-square-pen" />
              {{ $t('landing.edit') }}
            </NuxtLink>
          </div>
        </aside>
      </div>
    </div>

    <!-- Unenrol confirmation modal -->
    <div v-if="showUnenrolModal" id="smgp-unenrol-modal" class="smgp-modal">
      <div class="smgp-modal__backdrop" @click="showUnenrolModal = false" />
      <div class="smgp-modal__card">
        <div class="smgp-modal__header">
          <h3>{{ $t('landing.unenrol_confirm_title') || 'Confirm unenrolment' }}</h3>
          <button class="smgp-modal__close" @click="showUnenrolModal = false">
            <i class="icon-x" />
          </button>
        </div>
        <div class="smgp-modal__body">
          <p>{{ $t('landing.unenrol_confirm') || 'Are you sure you want to unenrol from this course?' }}</p>
        </div>
        <div class="smgp-modal__footer">
          <button class="btn btn-secondary" @click="showUnenrolModal = false">
            {{ $t('landing.cancel') || 'Cancel' }}
          </button>
          <button
            class="btn btn-danger"
            :disabled="unenrolling"
            @click="handleUnenrol"
          >
            <i class="icon-log-out" />
            {{ $t('landing.unenrol') || 'Unenrol' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add activity modal (Genially / Video URL) -->
    <AddActivityModal
      :open="addModalState.open"
      :mode="addModalState.mode"
      :sectionnum="addModalState.sectionnum"
      :saving="addingActivity"
      @close="addModalState.open = false"
      @save="onAddSave"
      @upload-fallback="onUploadFallback"
    />

    <!-- Delete activity confirmation modal -->
    <DeleteActivityModal
      :open="deleteState !== null"
      :activity-name="deleteState?.name ?? ''"
      :deleting="deletingActivity"
      @close="deleteState = null"
      @confirm="onDeleteConfirm"
    />
  </div>
</template>

<script setup lang="ts">
import AddActivityPicker from '~/components/course/AddActivityPicker.vue'
import AddActivityModal from '~/components/course/AddActivityModal.vue'
import DeleteActivityModal from '~/components/course/DeleteActivityModal.vue'
import type { ActivityType } from '~/components/course/activityTypes'

const route = useRoute()
const authStore = useAuthStore()
const { getCourseLandingData, enrolUser, unenrolUser, addActivity, deleteActivity } = useCourseApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const enrolling = ref(false)
const unenrolling = ref(false)
const showUnenrolModal = ref(false)
const expandedSections = ref<Record<number, boolean>>({})

// ── Inline editor state (admin) ───────────────────────────────────
// Single-open invariant: at most one section's picker is visible at a
// time. Set to a section.number to open, null to close.
const openPickerSection = ref<number | null>(null)

// State for the Genially / Video URL modal. `mode` decides the modal
// title and which input layout shows up.
const addModalState = ref<{
  open: boolean
  mode: 'genially' | 'video'
  sectionnum: number
  type: string  // The actual modname sent to add_activity ('genially' or 'url')
}>({ open: false, mode: 'genially', sectionnum: 0, type: 'genially' })
const addingActivity = ref(false)

// State for the delete confirmation modal — null means closed.
const deleteState = ref<{ cmid: number; name: string } | null>(null)
const deletingActivity = ref(false)

const courseid = computed(() => Number(route.params.id))

// SVG progress ring (radius 28, circumference 2πr)
const ringCircumference = computed(() => data.value?.ring_circumference ?? 2 * Math.PI * 28)
const ringOffset = computed(() => {
  if (data.value?.ring_offset !== undefined) return data.value.ring_offset
  const c = ringCircumference.value
  return c - (c * (data.value?.progress ?? 0)) / 100
})

const toggleSection = (num: number) => {
  expandedSections.value[num] = !expandedSections.value[num]
}

const enrol = async () => {
  enrolling.value = true
  const result = await enrolUser(courseid.value)
  enrolling.value = false
  if (result.error) {
    error.value = result.error
  } else {
    await fetchData()
  }
}

const handleUnenrol = async () => {
  unenrolling.value = true
  const result = await unenrolUser(courseid.value)
  unenrolling.value = false
  showUnenrolModal.value = false
  if (result.error) {
    error.value = result.error
  } else {
    await fetchData()
  }
}

// ── Inline editor handlers ────────────────────────────────────────

// Single-open toggle: clicking the same section closes the picker;
// clicking a different section reopens it there.
const togglePicker = (num: number) => {
  openPickerSection.value = openPickerSection.value === num ? null : num
}

// Build the URL to Moodle's standard /course/modedit.php form for a
// given modname + section. Mirrors the legacy behaviour
// (`amd/src/course_landing.js:218-222`): all non-inline activity types
// are handled by Moodle's own UI rather than going through our backend,
// because the backend would short-circuit `type='url'` with empty url
// and return an empty redirect, leaving the user stuck.
const redirectToModedit = (modname: string, sectionnum: number) => {
  const wwwroot = (authStore as { wwwroot?: string }).wwwroot ?? ''
  const params = new URLSearchParams({
    add: modname,
    type: '',
    course: String(courseid.value),
    section: String(sectionnum),
    return: '0',
  })
  window.location.href = `${wwwroot}/course/modedit.php?${params.toString()}`
}

// User picked one of the 25 activity types from the picker grid.
//   - Genially  → open the URL modal in genially mode (creates a mod_url
//                 with display=embed via add_activity)
//   - Video     → open the URL modal in video mode (also a mod_url, but
//                 the modal exposes a URL/upload tab toggle)
//   - Anything  → redirect straight to Moodle's standard add form for
//     else        that modname. We construct the URL client-side instead
//                 of calling add_activity, exactly like the legacy did.
const onActivitySelect = (type: ActivityType, sectionnum: number) => {
  openPickerSection.value = null

  if (type.isGenially) {
    addModalState.value = { open: true, mode: 'genially', sectionnum, type: 'genially' }
    return
  }
  if (type.isVideo) {
    addModalState.value = { open: true, mode: 'video', sectionnum, type: 'url' }
    return
  }

  redirectToModedit(type.mod, sectionnum)
}

// Save handler for the URL modal — sends the activity to the backend
// and refreshes the landing data on success.
const onAddSave = async ({ name, url }: { name: string; url: string }) => {
  addingActivity.value = true
  const result = await addActivity(
    courseid.value,
    addModalState.value.sectionnum,
    addModalState.value.type,
    name,
    url,
  )
  addingActivity.value = false
  if (result.error) {
    error.value = result.error
    return
  }
  addModalState.value.open = false
  await fetchData()
}

// User chose "Subir archivo" → drop them on Moodle's standard resource
// form so they can use the regular file picker.
const onUploadFallback = ({ sectionnum }: { sectionnum: number }) => {
  redirectToModedit('resource', sectionnum)
}

const onDeleteConfirm = async () => {
  if (!deleteState.value) return
  deletingActivity.value = true
  const result = await deleteActivity(deleteState.value.cmid)
  deletingActivity.value = false
  if (result.error) {
    error.value = result.error
    return
  }
  deleteState.value = null
  await fetchData()
}

const fetchData = async () => {
  // First fetch shows the spinner; subsequent refetches (after add /
  // delete) keep the page mounted so the user doesn't lose context.
  const isFirstFetch = data.value === null
  if (isFirstFetch) loading.value = true

  const result = await getCourseLandingData(courseid.value)
  loading.value = false

  if (result.error) {
    error.value = result.error
    return
  }

  data.value = result.data
  if (!data.value?.sections) return

  // Expand all sections on the very first fetch only — on subsequent
  // refetches we preserve whatever the user had open/collapsed so an
  // add/delete inside section 3 doesn't accidentally re-expand 1 and 2.
  if (isFirstFetch) {
    for (const section of data.value.sections) {
      expandedSections.value[section.number] = true
    }
  }
}

fetchData()
</script>
