<template>
  <div class="smgp-struct-editor">
    <!-- ── Editable mode ──────────────────────────────────────── -->
    <template v-if="!readonly">
      <div class="smgp-struct__list">
        <div
          v-for="sec in sections"
          :key="sec.sectionKey"
          class="smgp-struct__section"
          :class="{ 'is-disabled': !sec.included }"
        >
          <div class="smgp-struct__section-header">
            <i class="bi bi-list smgp-struct__handle" />
            <span class="form-switch smgp-struct__toggle">
              <input v-model="sec.included" class="form-check-input" type="checkbox">
            </span>
            <input
              v-model="sec.name"
              type="text"
              class="smgp-struct__name-input"
              :placeholder="sectionDisplayName(sec)"
            >
            <span class="smgp-struct__count">
              {{ sec.activities.length }} {{ activityCountLabel(sec.activities.length) }}
            </span>
            <button type="button" class="btn btn-sm smgp-struct__remove" :title="$t('restore.remove')" @click="confirmRemoveSection(sec)">
              <i class="bi bi-x-lg" />
            </button>
          </div>

          <div class="smgp-struct__activities">
            <div
              v-for="act in sec.activities"
              :key="act.actKey"
              class="smgp-struct__activity"
            >
              <i class="bi bi-list smgp-struct__handle" />
              <span class="smgp-struct__icon-wrap">
                <i :class="['bi', MOD_META[act.modname]?.icon || 'bi-puzzle']" />
              </span>
              <div class="smgp-struct__activity-info">
                <input
                  v-model="act.name"
                  type="text"
                  class="smgp-struct__act-name"
                  :placeholder="act.origName || $t('landing.activity_name_label')"
                >
                <span class="smgp-struct__modtype">
                  {{ MOD_META[act.modname]?.label || act.modname }}
                  <span v-if="act.deferred && !hideDeferredBadge" class="smgp-struct__deferred-badge" :title="$t('restore.activity_deferred_hint')">
                    <i class="bi bi-exclamation-triangle-fill" /> {{ $t('restore.deferred_badge') }}
                  </span>
                </span>
              </div>
              <span class="form-switch smgp-struct__toggle">
                <input v-model="act.included" class="form-check-input" type="checkbox">
              </span>
              <button
                v-if="act.cmid === 0"
                class="btn btn-sm smgp-struct__remove"
                :title="$t('restore.edit')"
                @click="openEditModal(sec, act)"
              >
                <i class="bi bi-pencil" />
              </button>
              <button type="button" class="btn btn-sm smgp-struct__remove" :title="$t('restore.remove')" @click="confirmRemoveActivity(sec, act)">
                <i class="bi bi-x-lg" />
              </button>
            </div>

            <button type="button" class="smgp-struct__add-act" @click="toggleActPicker(sec.sectionKey)">
              <i class="bi bi-plus" /> {{ $t('restore.add_activity') }}
            </button>
            <AddActivityPicker
              v-if="openActPickerKey === sec.sectionKey"
              @select="onActPickerSelect($event, sec)"
            />
          </div>
        </div>

        <button type="button" class="smgp-struct__add-sec" @click="addSection">
          <i class="bi bi-plus-circle" /> {{ $t('restore.add_section') }}
        </button>
      </div>

      <!-- Confirm delete modal -->
      <DeleteActivityModal
        :open="deleteTarget !== null"
        :activity-name="deleteTarget?.name ?? ''"
        @close="deleteTarget = null"
        @confirm="onDeleteConfirm"
      />

      <!-- Unified add/edit activity modal -->
      <AddActivityModal
        :open="addModalState.open"
        :mode="addModalState.mode"
        :layout="addModalState.layout"
        :sectionnum="0"
        :editing="addModalState.editing"
        :file-accept="addModalState.fileAccept"
        :initial="addModalState.initial"
        :deferred-hint="deferredHint || undefined"
        @close="addModalState.open = false"
        @save="onAddModalSave"
      />
    </template>

    <!-- ── Readonly mode (review) ──────────────────────────────── -->
    <template v-else>
      <div class="smgp-struct__list smgp-struct__list--readonly">
        <div
          v-for="sec in sections"
          :key="sec.sectionKey"
          class="smgp-struct__section"
          :class="{ 'is-disabled': !sec.included }"
        >
          <div class="smgp-struct__section-header">
            <i class="bi bi-layers smgp-struct__handle" />
            <i
              :class="['bi', sec.included ? 'bi-check-circle-fill' : 'bi-x-circle-fill', 'smgp-struct__review-state']"
              :style="{ color: sec.included ? '#10b981' : '#cbd5e1' }"
            />
            <strong class="smgp-struct__name-static">
              {{ sec.name || sectionDisplayName(sec) }}
            </strong>
            <span class="smgp-struct__count">
              {{ sec.activities.length }} {{ activityCountLabel(sec.activities.length) }}
            </span>
          </div>

          <div class="smgp-struct__activities">
            <div
              v-for="act in sec.activities"
              :key="act.actKey"
              class="smgp-struct__activity"
              :class="{ 'is-disabled': !act.included }"
            >
              <i
                :class="['bi', act.included ? 'bi-check-circle-fill' : 'bi-x-circle-fill', 'smgp-struct__review-state']"
                :style="{ color: act.included ? '#10b981' : '#cbd5e1' }"
              />
              <span class="smgp-struct__icon-wrap">
                <i :class="['bi', MOD_META[act.modname]?.icon || 'bi-puzzle']" />
              </span>
              <div class="smgp-struct__activity-info">
                <strong>{{ act.name || act.origName || '—' }}</strong>
                <span class="smgp-struct__modtype">{{ MOD_META[act.modname]?.label || act.modname }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import AddActivityPicker from '~/components/course/AddActivityPicker.vue'
import AddActivityModal from '~/components/course/AddActivityModal.vue'
import DeleteActivityModal from '~/components/course/DeleteActivityModal.vue'
import { ACTIVITY_TYPE_GROUPS, type ActivityType } from '~/components/course/activityTypes'
import {
  type SchemaSection,
  type SchemaActivity,
  type AddModalState,
  type DeleteTarget,
  MOD_META,
  sectionDisplayName,
  activityCountLabel,
  createBlankSection,
  defaultAddModalState,
} from '~/components/course/structureTypes'

const props = withDefaults(defineProps<{
  modelValue: SchemaSection[]
  readonly?: boolean
  /** Hide the yellow "deferred" badge on activities. On the create-course
   *  page all modules are created for real, so the badge is misleading. */
  hideDeferredBadge?: boolean
  /** Custom deferred-layout hint for the AddActivityModal. */
  deferredHint?: string
}>(), { readonly: false, hideDeferredBadge: false, deferredHint: '' })

const emit = defineEmits<{
  (e: 'update:modelValue', value: SchemaSection[]): void
}>()

// Proxy the sections array so mutations flow back via v-model.
const sections = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

// ── Add-section ──────────────────────────────────────────────────────
function addSection() {
  sections.value = [...sections.value, createBlankSection(sections.value)]
}

// ── Remove ───────────────────────────────────────────────────────────
function removeSection(sec: SchemaSection) {
  sections.value = sections.value.filter(s => s !== sec)
}
function removeActivity(sec: SchemaSection, act: SchemaActivity) {
  sec.activities = sec.activities.filter(a => a !== act)
}

// ── Confirm delete modal ─────────────────────────────────────────────
const deleteTarget = ref<DeleteTarget | null>(null)
function confirmRemoveSection(sec: SchemaSection) {
  deleteTarget.value = { kind: 'section', name: sec.name || sectionDisplayName(sec), sec }
}
function confirmRemoveActivity(sec: SchemaSection, act: SchemaActivity) {
  deleteTarget.value = { kind: 'activity', name: act.name || act.origName || '—', sec, act }
}
function onDeleteConfirm() {
  const t = deleteTarget.value
  if (!t) return
  if (t.kind === 'section') removeSection(t.sec)
  else if (t.act) removeActivity(t.sec, t.act)
  deleteTarget.value = null
}

// ── Activity picker + unified modal ──────────────────────────────────
const openActPickerKey = ref<string | null>(null)
function toggleActPicker(secKey: string) {
  openActPickerKey.value = openActPickerKey.value === secKey ? null : secKey
}

const addModalState = ref<AddModalState>(defaultAddModalState())

function onActPickerSelect(type: ActivityType, sec: SchemaSection) {
  openActPickerKey.value = null
  const layout = type.layout ?? 'url'
  const modeHint = type.isGenially ? 'genially' : type.isVideo ? 'video' : type.mod
  const persistMod = (type.isGenially || type.isVideo || type.mod === 'url') ? 'url' : type.mod
  addModalState.value = {
    open: true,
    mode: modeHint,
    layout,
    sectionKey: sec.sectionKey,
    modname: type.isGenially ? 'genially' : persistMod,
    editing: false,
    editKey: '',
    fileAccept: type.fileAccept ?? '',
    initial: null,
  }
}

function openEditModal(sec: SchemaSection, act: SchemaActivity) {
  let layout: 'url' | 'file' | 'body' | 'deferred' = 'url'
  let mode = act.modname
  let fileAccept = ''
  for (const group of ACTIVITY_TYPE_GROUPS) {
    for (const t of group.types) {
      const mnMatches = t.mod === act.modname ||
        (act.modname === 'genially' && t.isGenially) ||
        (act.modname === 'url' && (t.isVideo || t.mod === 'url'))
      if (mnMatches) {
        layout = t.layout ?? 'url'
        if (t.isGenially) mode = 'genially'
        else if (t.isVideo) mode = 'video'
        fileAccept = t.fileAccept ?? ''
        break
      }
    }
  }
  addModalState.value = {
    open: true,
    mode,
    layout,
    sectionKey: sec.sectionKey,
    modname: act.modname,
    editing: true,
    editKey: act.actKey,
    fileAccept,
    initial: {
      name: act.name,
      url: act.url,
      intro: act.intro,
      draftitemid: act.draftitemid,
      filename: act.filename,
    },
  }
}

function onAddModalSave(payload: {
  name: string
  url?: string
  intro?: string
  draftitemid?: number
  filename?: string
}) {
  const sec = sections.value.find(s => s.sectionKey === addModalState.value.sectionKey)
  if (!sec) {
    addModalState.value.open = false
    return
  }
  if (addModalState.value.editing) {
    const act = sec.activities.find(a => a.actKey === addModalState.value.editKey)
    if (act) {
      act.name = payload.name
      act.url = payload.url ?? act.url
      act.intro = payload.intro ?? act.intro
      act.draftitemid = payload.draftitemid ?? act.draftitemid
      act.filename = payload.filename ?? act.filename
    }
  } else {
    sec.activities.push({
      actKey: `new-act-${Date.now()}`,
      cmid: 0,
      modname: addModalState.value.modname,
      name: payload.name,
      origName: '',
      included: true,
      userinfo: false,
      userinfoAvailable: false,
      url: payload.url,
      intro: payload.intro,
      draftitemid: payload.draftitemid,
      filename: payload.filename,
      deferred: addModalState.value.layout === 'deferred',
    })
  }
  addModalState.value.open = false
}
</script>
