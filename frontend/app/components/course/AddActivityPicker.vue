<template>
  <div class="smgp-add-picker">
    <div class="smgp-add-grid">
      <template v-for="group in ACTIVITY_TYPE_GROUPS" :key="group.i18nKey">
        <h4
          class="smgp-add-grid__group-label"
          :style="{ color: group.color }"
        >
          {{ $t(group.i18nKey) }}
        </h4>
        <button
          v-for="type in group.types"
          :key="type.mod"
          type="button"
          class="smgp-add-grid__item"
          @click="$emit('select', type)"
        >
          <i
            class="smgp-add-grid__icon"
            :class="type.iconClass"
            :style="{ color: group.color }"
            aria-hidden="true"
          />
          <span class="smgp-add-grid__name">{{ $t(type.i18nKey) }}</span>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ACTIVITY_TYPE_GROUPS, type ActivityType } from './activityTypes'

defineEmits<{
  (e: 'select', type: ActivityType): void
}>()
</script>
