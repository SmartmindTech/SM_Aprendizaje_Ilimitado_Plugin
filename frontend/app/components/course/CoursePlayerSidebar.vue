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
      <template v-for="section in sections" :key="section.id">
        <div v-if="visibleActivities(section).length > 0" :title="section.name" class="text-truncate" style="color: green; font-size: 0.8rem; margin: 1rem 0 0 0.6rem;">{{section.name}}</div>
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
            <span class="smgp-course-activity__duration" />
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
        </button>
      </template>
    </div>
  </aside>
</template>

<script setup lang="ts">
defineProps<{
  sections: any[]
  selectedCmid: number
  collapsed: boolean
  completed: number
  total: number
}>()

defineEmits<{
  (e: 'select', activity: any): void
}>()

const visibleActivities = (section: any) =>
  (section.activities || []).filter((a: any) => !a.isforum && !a.islabel)
</script>
