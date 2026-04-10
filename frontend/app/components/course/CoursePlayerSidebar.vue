<template>
  <aside
    class="smgp-course-sidebar"
    :class="{ 'smgp-course-sidebar--collapsed': collapsed }"
  >
    <div class="smgp-course-sidebar__header">
      <span class="smgp-course-sidebar__title">
        {{ $t('course_page.module_content') }}
      </span>
      <span class="smgp-course-sidebar__count">
        {{ completed }}/{{ total }}
      </span>
    </div>

    <div class="smgp-course-sidebar__list">
      <template v-for="(section, idx) in sectionsWithActivities" :key="section.id">
        <!-- Collapsible section header with progress ring -->
        <button
          type="button"
          class="smgp-course-section"
          :class="{
            'smgp-course-section--last': idx === sectionsWithActivities.length - 1 && !isSectionOpen(section.id),
            'smgp-course-section--open': isSectionOpen(section.id),
          }"
          @click="toggleSection(section.id)"
        >
          <i
            class="smgp-course-section__chevron bi"
            :class="isSectionOpen(section.id) ? 'bi-chevron-down' : 'bi-chevron-right'"
          />
          <span class="smgp-course-section__name" :title="section.name">{{ section.name }}</span>
          <svg class="smgp-section-ring" viewBox="0 0 20 20">
            <circle class="smgp-section-ring__bg" cx="10" cy="10" r="7" fill="none" stroke-width="2" />
            <circle
              class="smgp-section-ring__fill"
              cx="10" cy="10" r="7"
              fill="none" stroke-width="2"
              :stroke-dasharray="CIRCUMFERENCE"
              :stroke-dashoffset="sectionRingOffset(section)"
            />
          </svg>
        </button>

        <!-- Activities (collapsed/expanded) -->
        <div
          class="smgp-course-section__activities"
          :class="{ 'smgp-course-section__activities--open': isSectionOpen(section.id) }"
        >
          <button
            v-for="activity in visibleActivities(section)"
            :key="activity.cmid"
            type="button"
            class="smgp-course-activity"
            :class="{
              'smgp-course-activity--complete': activity.iscomplete,
              'smgp-course-activity--active': selectedCmid === activity.cmid,
            }"
            @click="$emit('select', activity)"
          >
            <i :class="['smgp-course-activity__icon-box', activity.iconclass]" />
            <div class="smgp-course-activity__text">
              <p class="smgp-course-activity__name">{{ activity.name }}</p>
            </div>
            <i
              v-if="activity.iscomplete"
              class="bi bi-check-circle-fill smgp-course-activity__completion smgp-course-activity__check"
            />
            <svg
              v-else
              class="smgp-course-activity__completion smgp-activity-ring"
              viewBox="0 0 20 20"
            >
              <circle class="smgp-activity-ring__bg" cx="10" cy="10" r="7" fill="none" stroke-width="2" />
              <circle
                class="smgp-activity-ring__fill"
                cx="10" cy="10" r="7"
                fill="none" stroke-width="2"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="activityRingOffset(activity)"
              />
            </svg>
          </button>
        </div>
      </template>
    </div>
  </aside>
</template>

<script setup lang="ts">
const props = defineProps<{
  sections: any[]
  selectedCmid: number
  collapsed: boolean
  completed: number
  total: number
  activeCompleted?: number
  activeTotal?: number
  progressMap?: Map<number, { completed: number; total: number }>
}>()

defineEmits<{
  (e: 'select', activity: any): void
}>()

const CIRCUMFERENCE = 2 * Math.PI * 7 // ≈ 43.98

const visibleActivities = (section: any) =>
  (section.activities || []).filter((a: any) => !a.isforum && !a.islabel)

const sectionsWithActivities = computed(() =>
  props.sections.filter(s => visibleActivities(s).length > 0),
)

// ── Collapsible section state ────────────────────────────────────────
const openIds = ref<string[]>([])

function toggleSection(id: any) {
  const key = String(id)
  const idx = openIds.value.indexOf(key)
  if (idx >= 0) {
    openIds.value.splice(idx, 1)
  } else {
    openIds.value.push(key)
  }
}

function isSectionOpen(id: any) {
  return openIds.value.includes(String(id))
}

// Auto-open the section containing the selected activity.
watch(() => props.selectedCmid, (cmid) => {
  if (!cmid) return
  for (const section of props.sections) {
    const activities = visibleActivities(section)
    if (activities.some((a: any) => a.cmid === cmid)) {
      const key = String(section.id)
      if (!openIds.value.includes(key)) {
        openIds.value.push(key)
      }
      break
    }
  }
}, { immediate: true })

// ── Progress rings ───────────────────────────────────────────────────

/** Section ring: combines Moodle completion + granular sub-item progress. */
const sectionRingOffset = (section: any): number => {
  const acts = visibleActivities(section)
  if (acts.length === 0) return CIRCUMFERENCE
  let totalProgress = 0
  for (const act of acts) {
    if (act.iscomplete) {
      totalProgress += 1
    } else {
      let completed = 0
      let total = 0
      if (act.cmid === props.selectedCmid) {
        completed = props.activeCompleted ?? 0
        total = props.activeTotal ?? 0
      } else if (props.progressMap?.has(act.cmid)) {
        const saved = props.progressMap.get(act.cmid)!
        completed = saved.completed
        total = saved.total
      }
      if (total > 0 && completed > 0) {
        totalProgress += Math.min(completed / total, 1)
      }
    }
  }
  const pct = totalProgress / acts.length
  return CIRCUMFERENCE - (CIRCUMFERENCE * pct)
}

/** Activity ring: proportional fill for active/visited, empty for unvisited. */
const activityRingOffset = (activity: any): number => {
  let completed = 0
  let total = 0
  if (activity.cmid === props.selectedCmid) {
    completed = props.activeCompleted ?? 0
    total = props.activeTotal ?? 0
  } else if (props.progressMap?.has(activity.cmid)) {
    const saved = props.progressMap.get(activity.cmid)!
    completed = saved.completed
    total = saved.total
  }
  if (total > 0 && completed > 0) {
    const pct = Math.min(completed / total, 1)
    return CIRCUMFERENCE - (CIRCUMFERENCE * pct)
  }
  return CIRCUMFERENCE
}
</script>

<style scoped lang="scss">
.smgp-section-ring {
  flex-shrink: 0;
  margin-left: auto;
  width: 16px;
  height: 16px;
  transform: rotate(-90deg);

  &__bg {
    stroke: #E2DDD6;
  }
  &__fill {
    stroke: #10b981;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.4s ease;
  }
}
</style>
