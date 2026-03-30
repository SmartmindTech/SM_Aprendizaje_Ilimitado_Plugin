<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="smgp-landing" :data-courseid="data.courseid">
    <h2 class="smgp-landing__heading">
      <i class="icon-notebook-text" />
      {{ $t('landing.program_content') || 'Program Content' }}
    </h2>

    <div v-if="data.hassummary" class="smgp-landing__summary" v-html="data.coursesummary" />

    <div class="smgp-landing__body">
      <!-- Left column: program content -->
      <div class="smgp-landing__main">
        <div class="smgp-landing__sections">
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
              <div class="smgp-landing__section-title">
                <span class="smgp-landing__section-name">{{ section.name }}</span>
                <span class="smgp-landing__section-count">
                  {{ section.activity_count }} {{ $t('landing.modules') || 'modules' }}
                </span>
              </div>
              <i class="icon-chevron-down smgp-landing__chevron" />
            </div>
            <div v-if="expandedSections[section.number] && section.hasactivities" class="smgp-landing__section-body">
              <ul class="smgp-landing__activities">
                <li
                  v-for="activity in section.activities"
                  :key="activity.cmid"
                  class="smgp-landing__activity"
                  :data-cmid="activity.cmid"
                >
                  <i :class="activity.iconclass" class="smgp-landing__activity-icon" />
                  <span class="smgp-landing__activity-name">{{ activity.name }}</span>
                  <span class="smgp-landing__activity-type">{{ activity.modtypelabel }}</span>
                  <button
                    v-if="data.canedit"
                    class="smgp-landing__delete-btn"
                    :data-cmid="activity.cmid"
                    :title="$t('landing.delete_activity') || 'Delete activity'"
                  >
                    <i class="icon-circle-x" />
                  </button>
                </li>
              </ul>
            </div>
            <button
              v-if="data.canedit"
              class="smgp-landing__add-btn"
              :data-sectionnum="section.number"
              :data-courseid="data.courseid"
            >
              <i class="icon-circle-plus" />
              {{ $t('landing.add_activity') || 'Add activity' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Right column: course info sidebar -->
      <div class="smgp-landing__sidebar">
        <div v-if="data.hasimage" class="smgp-landing__image">
          <img :src="data.courseimageurl" :alt="data.coursename">
        </div>

        <div class="smgp-landing__info-card">
          <div class="smgp-landing__info-header">
            <h3 class="smgp-landing__info-title">
              {{ $t('landing.course_info') || 'Course information' }}
            </h3>
            <span v-if="data.is_enrolled_real" class="smgp-landing__enrolled-badge">
              <i class="icon-check-circle" />
              {{ $t('landing.enrolled_badge') || 'Enrolled' }}
            </span>
          </div>

          <div class="smgp-landing__info-rows">
            <div v-if="data.has_course_category" class="smgp-landing__info-row">
              <i class="icon-bookmark" />
              <span class="smgp-landing__info-label">{{ $t('landing.category') || 'Category' }}</span>
              <span class="smgp-landing__info-value">{{ data.course_category }}</span>
            </div>

            <div class="smgp-landing__info-row">
              <i class="icon-gauge" />
              <span class="smgp-landing__info-label">{{ $t('landing.level') || 'Level' }}</span>
              <span class="smgp-landing__info-value">{{ data.level_label }}</span>
            </div>

            <div class="smgp-landing__info-row">
              <i class="icon-layout-grid" />
              <span class="smgp-landing__info-label">{{ $t('landing.modules') || 'Modules' }}</span>
              <span class="smgp-landing__info-value">{{ data.total_activities }}</span>
            </div>

            <div class="smgp-landing__info-row">
              <i class="icon-list-checks" />
              <span class="smgp-landing__info-label">{{ $t('landing.sections') || 'Sections' }}</span>
              <span class="smgp-landing__info-value">{{ data.section_count }}</span>
            </div>

            <div v-if="data.has_duration" class="smgp-landing__info-row">
              <i class="icon-clock" />
              <span class="smgp-landing__info-label">{{ $t('landing.duration') || 'Duration' }}</span>
              <span class="smgp-landing__info-value">{{ data.duration_hours }}h</span>
            </div>

            <div class="smgp-landing__info-row">
              <i class="icon-languages" />
              <span class="smgp-landing__info-label">{{ $t('landing.language') || 'Language' }}</span>
              <span class="smgp-landing__info-value">{{ data.language }}</span>
            </div>

            <div v-if="data.has_smartmind_code" class="smgp-landing__info-row">
              <i class="icon-qr-code" />
              <span class="smgp-landing__info-label">{{ $t('landing.smartmind_code') || 'SmartMind code' }}</span>
              <span class="smgp-landing__info-value">{{ data.smartmind_code }}</span>
            </div>

            <div v-if="data.has_sepe" class="smgp-landing__info-row">
              <i class="icon-file-code" />
              <span class="smgp-landing__info-label">{{ $t('landing.sepe_code') || 'SEPE code' }}</span>
              <span class="smgp-landing__info-value">{{ data.sepe_code }}</span>
            </div>
          </div>
        </div>

        <!-- Enrolled user: progress + next activity -->
        <div v-if="data.is_enrolled_real" class="smgp-landing__enrolled-info">
          <div v-if="data.has_next_activity" class="smgp-landing__info-row">
            <i class="icon-arrow-right-circle" />
            <span class="smgp-landing__info-label">{{ $t('landing.next_activity') || 'Next activity' }}</span>
            <span class="smgp-landing__info-value">{{ data.next_activity_name }}</span>
          </div>

          <div class="smgp-landing__progress-section">
            <span class="smgp-landing__progress-label">{{ $t('landing.progress') || 'Progress' }}</span>
            <div class="progress smgp-landing__progress-bar">
              <div
                class="progress-bar"
                role="progressbar"
                :style="{ width: data.progress + '%' }"
                :aria-valuenow="data.progress"
                aria-valuemin="0"
                aria-valuemax="100"
              />
            </div>
            <span class="smgp-landing__progress-pct">{{ data.progress }}%</span>
          </div>
        </div>

        <div class="smgp-landing__action">
          <!-- Enrolled real -->
          <template v-if="data.is_enrolled_real">
            <NuxtLink
              v-if="data.has_started"
              :to="`/courses/${data.courseid}/player`"
              class="smgp-landing__btn smgp-landing__btn--primary"
            >
              <i class="icon-play" />
              {{ $t('landing.continue') || 'Continue' }}
            </NuxtLink>
            <NuxtLink
              v-else
              :to="`/courses/${data.courseid}/player`"
              class="smgp-landing__btn smgp-landing__btn--primary"
            >
              <i class="icon-circle-play" />
              {{ $t('landing.start') || 'Start' }}
            </NuxtLink>
            <button
              type="button"
              class="smgp-landing__btn smgp-landing__btn--danger"
              @click="showUnenrolModal = true"
            >
              <i class="icon-log-out" />
              {{ $t('landing.unenrol') || 'Unenrol' }}
            </button>
          </template>

          <!-- Not enrolled real but enrolled (e.g. guest) -->
          <template v-else-if="data.is_enrolled">
            <NuxtLink
              :to="`/courses/${data.courseid}/player`"
              class="smgp-landing__btn smgp-landing__btn--primary"
            >
              <i class="icon-circle-play" />
              {{ $t('landing.view_course') || 'View course' }}
            </NuxtLink>
          </template>

          <!-- Not enrolled -->
          <template v-else>
            <button
              type="button"
              class="smgp-landing__btn smgp-landing__btn--primary"
              :data-courseid="data.courseid"
              :disabled="enrolling"
              @click="enrol"
            >
              <span v-if="enrolling" class="spinner-border spinner-border-sm me-1" />
              <i v-else class="icon-user-plus" />
              {{ $t('landing.enrol') || 'Enrol' }}
            </button>
          </template>
        </div>

        <div v-if="data.canedit" class="smgp-landing__action" style="margin-top: 0.75rem;">
          <a :href="data.edit_course_url" class="smgp-landing__btn smgp-landing__btn--secondary">
            <i class="icon-square-pen" />
            {{ $t('landing.edit') || 'Edit course' }}
          </a>
        </div>
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
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const { getCourseLandingData, enrolUser, unenrolUser } = useCourseApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const enrolling = ref(false)
const unenrolling = ref(false)
const showUnenrolModal = ref(false)
const expandedSections = ref<Record<number, boolean>>({})

const courseid = computed(() => Number(route.params.id))

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

const fetchData = async () => {
  loading.value = true
  const result = await getCourseLandingData(courseid.value)
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
    // Expand all sections by default
    if (data.value?.sections) {
      for (const section of data.value.sections) {
        expandedSections.value[section.number] = true
      }
    }
  }
}

onMounted(fetchData)
</script>
