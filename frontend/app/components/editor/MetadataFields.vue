<template>
  <div class="smgp-meta">
    <h3 class="smgp-meta__heading">
      <i class="icon-settings" />
      {{ $t('editor.metadata') || 'SmartMind metadata' }}
    </h3>
    <div class="smgp-meta__grid">
      <div class="smgp-meta__field">
        <label>{{ $t('editor.duration_hours') || 'Duration (hours)' }}</label>
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
        <label>{{ $t('editor.level') || 'Level' }}</label>
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
        <label>{{ $t('editor.completion_pct') || 'Completion %' }}</label>
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
        <label>{{ $t('editor.category') || 'Catalogue category' }}</label>
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
        <label>{{ $t('editor.smartmind_code') || 'SmartMind code' }}</label>
        <input
          type="text"
          class="form-control"
          :value="modelValue.smartmind_code"
          @input="update('smartmind_code', ($event.target as HTMLInputElement).value)"
        >
      </div>
      <div class="smgp-meta__field">
        <label>{{ $t('editor.sepe_code') || 'SEPE code' }}</label>
        <input
          type="text"
          class="form-control"
          :value="modelValue.sepe_code"
          @input="update('sepe_code', ($event.target as HTMLInputElement).value)"
        >
      </div>
    </div>
    <div class="smgp-meta__field">
      <label>{{ $t('editor.description') || 'Description' }}</label>
      <textarea
        rows="4"
        class="form-control"
        :value="modelValue.description"
        @input="update('description', ($event.target as HTMLTextAreaElement).value)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
interface MetaFields {
  duration_hours: number
  level: string
  completion_percentage: number
  smartmind_code: string
  sepe_code: string
  description: string
  course_category: number
}

const props = defineProps<{
  modelValue: MetaFields
  categories: Array<{ id: number; name: string }>
}>()
const emit = defineEmits<{ (e: 'update:modelValue', value: MetaFields): void }>()

function update<K extends keyof MetaFields>(key: K, value: MetaFields[K]) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>

<style scoped lang="scss">
.smgp-meta {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
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
    label { display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.9rem; }
  }
}
</style>
