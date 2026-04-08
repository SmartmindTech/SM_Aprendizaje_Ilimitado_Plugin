<template>
  <div v-if="open" class="smgp-modal">
    <div class="smgp-modal__backdrop" @click="$emit('close')" />
    <div class="smgp-modal__card">
      <div class="smgp-modal__header">
        <h3>
          <i
            :class="mode === 'video' ? 'icon-film' : 'icon-presentation'"
            :style="{ color: mode === 'video' ? '#3b82f6' : '#f97316', marginRight: '0.5rem' }"
            aria-hidden="true"
          />
          {{ mode === 'video' ? $t('landing.add_activity_modal_title_video') : $t('landing.add_activity_modal_title_genially') }}
        </h3>
        <button class="smgp-modal__close" @click="$emit('close')">
          <i class="icon-x" />
        </button>
      </div>

      <div class="smgp-modal__body">
        <!-- Activity name -->
        <div class="smgp-form-group">
          <label class="smgp-form-label">{{ $t('landing.activity_name_label') }}</label>
          <input
            ref="nameInput"
            v-model="name"
            type="text"
            class="form-control"
            :placeholder="$t('landing.activity_name_label')"
          >
        </div>

        <!-- Tabs (only shown for video — Genially has a single URL flow) -->
        <div v-if="mode === 'video'" class="smgp-tab-row">
          <button
            type="button"
            class="smgp-tab"
            :class="{ 'smgp-tab--active': uploadMode === 'url' }"
            @click="uploadMode = 'url'"
          >
            {{ $t('landing.video_tab_url') }}
          </button>
          <button
            type="button"
            class="smgp-tab"
            :class="{ 'smgp-tab--active': uploadMode === 'upload' }"
            @click="uploadMode = 'upload'"
          >
            {{ $t('landing.video_tab_upload') }}
          </button>
        </div>

        <!-- URL input (Genially always, Video when uploadMode === 'url') -->
        <div v-if="mode !== 'video' || uploadMode === 'url'" class="smgp-form-group">
          <label class="smgp-form-label">{{ $t('landing.activity_url_label') }}</label>
          <input
            v-model="url"
            type="url"
            class="form-control"
            :placeholder="urlPlaceholder"
          >
          <small class="smgp-form-hint">{{ urlHint }}</small>
        </div>

        <!-- Upload fallback (Video only) — drop zone is decorative; the
             actual upload deferes to Moodle's modedit.php form. -->
        <div v-if="mode === 'video' && uploadMode === 'upload'" class="smgp-form-group">
          <div class="smgp-upload-zone smgp-upload-zone--decorative">
            <i class="icon-upload" aria-hidden="true" />
            <p>{{ $t('landing.video_upload_hint') }}</p>
          </div>
        </div>
      </div>

      <div class="smgp-modal__footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ $t('landing.cancel') }}
        </button>
        <button
          v-if="mode === 'video' && uploadMode === 'upload'"
          type="button"
          class="btn btn-primary"
          @click="$emit('uploadFallback', { sectionnum })"
        >
          <i class="icon-external-link" aria-hidden="true" />
          {{ $t('landing.video_upload_continue') }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn-primary"
          :disabled="!canSave || saving"
          @click="onSave"
        >
          <i class="icon-check" aria-hidden="true" />
          {{ $t('landing.save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  open: boolean
  mode: 'genially' | 'video'
  sectionnum: number
  saving?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: { name: string; url: string }): void
  (e: 'uploadFallback', payload: { sectionnum: number }): void
}>()

const { t } = useI18n()

const name = ref('')
const url = ref('')
const uploadMode = ref<'url' | 'upload'>('url')
const nameInput = ref<HTMLInputElement | null>(null)

const urlPlaceholder = computed(() =>
  props.mode === 'video'
    ? 'https://www.youtube.com/watch?v=...'
    : 'https://view.genial.ly/...'
)

const urlHint = computed(() =>
  props.mode === 'video'
    ? t('landing.activity_url_hint_video')
    : t('landing.activity_url_hint_genially')
)

const canSave = computed(() => name.value.trim().length > 0 && url.value.trim().length > 0)

const onSave = () => {
  if (!canSave.value) return
  emit('save', { name: name.value.trim(), url: url.value.trim() })
}

// Reset whenever the modal is (re)opened so a previous Genially session
// doesn't leak its name/url into the next Video session.
watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) return
    name.value = ''
    url.value = ''
    uploadMode.value = 'url'
    await nextTick()
    nameInput.value?.focus()
  },
)
</script>
