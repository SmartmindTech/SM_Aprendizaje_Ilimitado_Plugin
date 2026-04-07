<template>
  <div class="smgp-objectives">
    <h3 class="smgp-objectives__heading">
      <i class="icon-list-checks" />
      {{ $t('editor.objectives') || 'Learning objectives' }}
    </h3>
    <ul class="smgp-objectives__list">
      <li
        v-for="(obj, idx) in modelValue"
        :key="idx"
        class="smgp-objectives__row"
        draggable="true"
        @dragstart="onDragStart(idx, $event)"
        @dragover.prevent="onDragOver(idx, $event)"
        @drop="onDrop(idx, $event)"
        @dragend="onDragEnd"
      >
        <span class="smgp-objectives__handle" title="Drag">&#x2630;</span>
        <input
          class="smgp-objectives__input form-control"
          :value="obj"
          type="text"
          :placeholder="$t('editor.objective_placeholder') || 'Type a learning objective…'"
          @input="update(idx, ($event.target as HTMLInputElement).value)"
        >
        <button
          type="button"
          class="smgp-objectives__remove btn btn-sm btn-outline-danger"
          :title="$t('editor.remove') || 'Remove'"
          @click="remove(idx)"
        >
          &times;
        </button>
      </li>
    </ul>
    <button type="button" class="btn btn-sm btn-outline-primary" @click="add">
      <i class="icon-plus" /> {{ $t('editor.add_objective') || 'Add objective' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ modelValue: string[] }>()
const emit = defineEmits<{ (e: 'update:modelValue', value: string[]): void }>()

const dragIndex = ref<number | null>(null)

function update(idx: number, text: string) {
  const next = [...props.modelValue]
  next[idx] = text
  emit('update:modelValue', next)
}
function add() {
  emit('update:modelValue', [...props.modelValue, ''])
}
function remove(idx: number) {
  const next = [...props.modelValue]
  next.splice(idx, 1)
  emit('update:modelValue', next)
}
function onDragStart(idx: number, e: DragEvent) {
  dragIndex.value = idx
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}
function onDragOver(_idx: number, e: DragEvent) {
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
}
function onDrop(idx: number, _e: DragEvent) {
  const src = dragIndex.value
  if (src === null || src === idx) return
  const next = [...props.modelValue]
  const [moved] = next.splice(src, 1)
  next.splice(idx, 0, moved)
  emit('update:modelValue', next)
  dragIndex.value = null
}
function onDragEnd() {
  dragIndex.value = null
}
</script>

<style scoped lang="scss">
.smgp-objectives {
  background: #fff;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);

  &__heading { font-size: 1rem; font-weight: 600; color: #1e293b; margin: 0 0 0.75rem; }
  &__list { list-style: none; padding: 0; margin: 0 0 0.75rem; }
  &__row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
    padding: 0.4rem;
    background: #f8fafc;
    border-radius: 8px;
    &:hover { background: #f1f5f9; }
  }
  &__handle { cursor: grab; color: #94a3b8; user-select: none; }
  &__input { flex: 1; }
  &__remove { flex-shrink: 0; }
}
</style>
