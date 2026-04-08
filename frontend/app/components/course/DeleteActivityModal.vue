<template>
  <div v-if="open" class="smgp-modal">
    <div class="smgp-modal__backdrop" @click="$emit('close')" />
    <div class="smgp-modal__card">
      <div class="smgp-modal__header">
        <h3>{{ $t('landing.delete_activity_title') }}</h3>
        <button class="smgp-modal__close" @click="$emit('close')">
          <i class="icon-x" />
        </button>
      </div>
      <div class="smgp-modal__body">
        <p>{{ $t('landing.delete_activity_confirm', { name: activityName }) }}</p>
      </div>
      <div class="smgp-modal__footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ $t('landing.cancel') }}
        </button>
        <button
          type="button"
          class="btn btn-danger"
          :disabled="deleting"
          @click="$emit('confirm')"
        >
          <i class="icon-trash-2" aria-hidden="true" />
          {{ $t('landing.delete') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  open: boolean
  activityName: string
  deleting?: boolean
}>()

defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()
</script>
