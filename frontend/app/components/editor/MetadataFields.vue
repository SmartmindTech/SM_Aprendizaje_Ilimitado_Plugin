<template>
  <div class="smgp-meta">
    <h3 class="smgp-meta__heading">
      <i class="icon-settings" />
      {{ $t('editor.metadata') || 'SmartMind metadata' }}
    </h3>
    <!-- Content type toggle: regular course vs SmartMind pill. Sits at
         the top of the meta block because it changes how the rest of
         the form should be interpreted (a píldora is just a course
         with shorter duration, but the explicit flag drives badges and
         filtering elsewhere in the SPA). -->
    <div class="smgp-meta__field smgp-meta__field--toggle">
      <div class="form-check form-switch">
        <input
          id="meta-is-pill"
          type="checkbox"
          class="form-check-input"
          :checked="modelValue.is_pill === 1"
          @change="update('is_pill', ($event.target as HTMLInputElement).checked ? 1 : 0)"
        >
        <label class="form-check-label fw-bold" for="meta-is-pill">
          {{ $t('editor.is_pill_label') || 'This is a pill' }}
        </label>
      </div>
      <p class="smgp-meta__help">
        {{ $t('editor.is_pill_help') || 'Tick this box if the content is a SmartMind pill (microlearning short course). Off by default — every new course starts as a regular course.' }}
      </p>
    </div>
    <div class="smgp-meta__grid">
      <div class="smgp-meta__field">
        <label><i v-if="rich" class="bi bi-clock" /> {{ $t('editor.duration_hours') || 'Duration (hours)' }} <i v-if="rich" class="bi bi-info-circle smgp-meta__tip" :title="$t('editor.tip_duration_hours')" /></label>
        <input
          type="number"
          min="0"
          step="0.1"
          class="form-control"
          :value="modelValue.duration_hours"
          @input="update('duration_hours', Number(($event.target as HTMLInputElement).value))"
        >
      </div>
      <div class="smgp-meta__field">
        <label><i v-if="rich" class="bi bi-bar-chart-steps" /> {{ $t('editor.level') || 'Level' }} <i v-if="rich" class="bi bi-info-circle smgp-meta__tip" :title="$t('editor.tip_level')" /></label>
        <select
          class="form-control"
          :value="modelValue.level"
          @change="update('level', ($event.target as HTMLSelectElement).value)"
        >
          <option value="beginner">{{ $t('editor.level_beginner') || 'Beginner' }}</option>
          <option value="medium">{{ $t('editor.level_medium') || 'Medium' }}</option>
          <option value="advanced">{{ $t('editor.level_advanced') || 'Advanced' }}</option>
        </select>
      </div>
      <div class="smgp-meta__field">
        <label><i v-if="rich" class="bi bi-patch-check" /> {{ $t('editor.completion_pct') || 'Completion %' }} <i v-if="rich" class="bi bi-info-circle smgp-meta__tip" :title="$t('editor.tip_completion_pct')" /></label>
        <input
          type="number"
          min="0"
          max="100"
          class="form-control"
          :value="modelValue.completion_percentage"
          @input="update('completion_percentage', Number(($event.target as HTMLInputElement).value))"
        >
      </div>
      <div class="smgp-meta__field">
        <label><i v-if="rich" class="bi bi-bookmark" /> {{ $t('editor.category') || 'Catalogue category' }} <i v-if="rich" class="bi bi-info-circle smgp-meta__tip" :title="$t('editor.tip_category')" /></label>
        <select
          class="form-control"
          :value="modelValue.course_category"
          @change="update('course_category', Number(($event.target as HTMLSelectElement).value))"
        >
          <option :value="0">—</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>
      <div class="smgp-meta__field">
        <label><i v-if="rich" class="bi bi-upc-scan" /> {{ $t('editor.smartmind_code') || 'SmartMind code' }} <i v-if="rich" class="bi bi-info-circle smgp-meta__tip" :title="$t('editor.tip_smartmind_code')" /></label>
        <input
          type="text"
          class="form-control"
          :value="modelValue.smartmind_code"
          @input="update('smartmind_code', ($event.target as HTMLInputElement).value)"
        >
      </div>
      <div class="smgp-meta__field">
        <label><i v-if="rich" class="bi bi-file-earmark-code" /> {{ $t('editor.sepe_code') || 'SEPE code' }} <i v-if="rich" class="bi bi-info-circle smgp-meta__tip" :title="$t('editor.tip_sepe_code')" /></label>
        <input
          type="text"
          class="form-control"
          :value="modelValue.sepe_code"
          @input="update('sepe_code', ($event.target as HTMLInputElement).value)"
        >
      </div>
    </div>
    <div class="smgp-meta__field">
      <label><i v-if="rich" class="bi bi-file-text" /> {{ $t('editor.description') || 'Description' }} <i v-if="rich" class="bi bi-info-circle smgp-meta__tip" :title="$t('editor.tip_description')" /></label>
      <textarea
        v-if="!rich"
        rows="4"
        class="form-control"
        :value="modelValue.description"
        @input="update('description', ($event.target as HTMLTextAreaElement).value)"
      />
      <div v-if="rich" class="smgp-meta__rte">
        <div class="smgp-meta__rte-toolbar">
          <button type="button" :title="$t('editor.rte_bold')" @click="exec('bold')"><i class="bi bi-type-bold" /></button>
          <button type="button" :title="$t('editor.rte_italic')" @click="exec('italic')"><i class="bi bi-type-italic" /></button>
          <button type="button" :title="$t('editor.rte_underline')" @click="exec('underline')"><i class="bi bi-type-underline" /></button>
          <button type="button" :title="$t('editor.rte_strike')" @click="exec('strikeThrough')"><i class="bi bi-type-strikethrough" /></button>
          <span class="smgp-meta__rte-sep" />
          <button type="button" :title="$t('editor.rte_ul')" @click="exec('insertUnorderedList')"><i class="bi bi-list-ul" /></button>
          <button type="button" :title="$t('editor.rte_ol')" @click="exec('insertOrderedList')"><i class="bi bi-list-ol" /></button>
          <span class="smgp-meta__rte-sep" />
          <button type="button" :title="$t('editor.rte_link')" @click="insertLink"><i class="bi bi-link-45deg" /></button>
          <button type="button" :title="$t('editor.rte_clear')" @click="exec('removeFormat')"><i class="bi bi-eraser" /></button>
        </div>
        <div
          ref="rteEl"
          class="smgp-meta__rte-body form-control"
          contenteditable="true"
          @input="onRteInput"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'

interface MetaFields {
  duration_hours: number
  level: string
  completion_percentage: number
  is_pill: number
  smartmind_code: string
  sepe_code: string
  description: string
  course_category: number
}

const props = withDefaults(defineProps<{
  modelValue: MetaFields
  categories: Array<{ id: number; name: string }>
  /** When true, show icons, tooltips, and the rich-text editor for
   *  the description. Defaults to false (plain textarea, matching
   *  the edit-page layout). The restore wizard passes true. */
  rich?: boolean
}>(), { rich: false })
const emit = defineEmits<{ (e: 'update:modelValue', value: MetaFields): void }>()

function update<K extends keyof MetaFields>(key: K, value: MetaFields[K]) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

// Rich text editor (contenteditable + execCommand for simplicity).
const rteEl = ref<HTMLDivElement | null>(null)
function syncRteFromModel() {
  if (rteEl.value && rteEl.value.innerHTML !== (props.modelValue.description || '')) {
    rteEl.value.innerHTML = props.modelValue.description || ''
  }
}
onMounted(syncRteFromModel)
watch(() => props.modelValue.description, syncRteFromModel)
function onRteInput() {
  if (!rteEl.value) return
  update('description', rteEl.value.innerHTML)
}
function exec(cmd: string) {
  rteEl.value?.focus()
  document.execCommand(cmd, false)
  onRteInput()
}
function insertLink() {
  const url = window.prompt('URL', 'https://')
  if (!url) return
  rteEl.value?.focus()
  document.execCommand('createLink', false, url)
  onRteInput()
}
</script>

<style scoped lang="scss">
.smgp-meta {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.25rem;
  margin-bottom: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  &__heading { font-size: 1rem; font-weight: 600; color: #1e293b; margin: 0 0 0.75rem; }
  &__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    @media (max-width: 700px) { grid-template-columns: 1fr; }
  }
  &__field {
    margin-bottom: 0.75rem;
    label { display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.9rem; color: #1e293b; }

    &--toggle {
      padding: 0.75rem 1rem;
      background: rgba(16, 185, 129, 0.04);
      border: 1px solid rgba(16, 185, 129, 0.18);
      border-radius: 8px;
      margin-bottom: 1rem;
      .form-check-label { margin-bottom: 0; }
    }
  }
  &__help {
    margin: 0.4rem 0 0;
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.4;
  }
  &__tip {
    color: #94a3b8;
    font-size: 0.7rem;
    cursor: help;
    margin-left: 0.15rem;
  }
  :deep(.form-control),
  :deep(.form-select) {
    background-color: #fff !important;
  }
  :deep(select.form-control),
  :deep(.form-select) {
    cursor: pointer;
  }
  &__rte {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
  }
  &__rte-toolbar {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.4rem 0.5rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    button {
      background: transparent;
      border: none;
      width: 28px;
      height: 28px;
      border-radius: 4px;
      color: #475569;
      cursor: pointer;
      font-size: 0.85rem;
      &:hover { background: #e2e8f0; }
    }
  }
  &__rte-sep {
    width: 1px;
    height: 18px;
    background: #cbd5e1;
    margin: 0 0.25rem;
  }
  &__rte-body {
    min-height: 140px;
    padding: 0.65rem 0.85rem;
    background: #fff !important;
    border: none !important;
    border-radius: 0 !important;
    outline: none;
    &:focus { box-shadow: none; }
  }
}
</style>
