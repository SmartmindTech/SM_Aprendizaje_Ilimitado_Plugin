<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <form v-else class="smgp-course-editor" @submit.prevent="onSubmit">
    <div class="smgp-course-editor__header">
      <h1 class="smgp-course-editor__title">
        {{ isNew ? ($t('editor.new_course') || 'New course') : ($t('editor.edit_course') || 'Edit course') }}
      </h1>
      <NuxtLink :to="`/courses/${courseid || '0'}/landing`" class="btn btn-outline-secondary btn-sm">
        <i class="icon-arrow-left" /> {{ $t('editor.cancel') || 'Cancel' }}
      </NuxtLink>
    </div>

    <div class="smgp-course-editor__section">
      <h3 class="smgp-course-editor__section-title">
        <i class="icon-info" /> {{ $t('editor.core_fields') || 'Core fields' }}
      </h3>
      <div class="smgp-course-editor__grid">
        <div class="smgp-course-editor__field">
          <label>{{ $t('editor.fullname') || 'Full name' }}</label>
          <input v-model="form.fullname" type="text" class="form-control" required>
        </div>
        <div class="smgp-course-editor__field">
          <label>{{ $t('editor.shortname') || 'Short name' }}</label>
          <input v-model="form.shortname" type="text" class="form-control" required>
        </div>
        <div class="smgp-course-editor__field">
          <label>{{ $t('editor.moodle_category') || 'Moodle category' }}</label>
          <select v-model="form.categoryid" class="form-control" required>
            <option v-for="cat in data?.moodle_categories ?? []" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>
        <div class="smgp-course-editor__field">
          <label>{{ $t('editor.visible') || 'Visible' }}</label>
          <select v-model.number="form.visible" class="form-control">
            <option :value="1">{{ $t('editor.yes') || 'Yes' }}</option>
            <option :value="0">{{ $t('editor.no') || 'No' }}</option>
          </select>
        </div>
      </div>
      <div class="smgp-course-editor__field">
        <label>{{ $t('editor.summary') || 'Summary' }}</label>
        <textarea v-model="form.summary" rows="4" class="form-control" />
      </div>
    </div>

    <div class="smgp-course-editor__section">
      <MetadataFields v-model="metaModel" :categories="data?.smgp_categories ?? []" />
    </div>

    <div class="smgp-course-editor__section">
      <ObjectivesEditor v-model="objectivesModel" />
    </div>

    <div class="smgp-course-editor__actions">
      <button type="submit" class="btn btn-primary" :disabled="saving">
        <i class="icon-save" /> {{ saving ? ($t('editor.saving') || 'Saving…') : ($t('editor.save') || 'Save course') }}
      </button>
    </div>

    <div v-if="saveError" class="alert alert-danger mt-3">{{ saveError }}</div>
  </form>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMoodleAjax } from '~/composables/api_calls/useMoodleAjax'
import ObjectivesEditor from '~/components/editor/ObjectivesEditor.vue'
import MetadataFields from '~/components/editor/MetadataFields.vue'

definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const router = useRouter()
const { call } = useMoodleAjax()

const courseid = computed(() => Number(route.params.id) || 0)
const isNew = computed(() => courseid.value === 0)

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

const form = reactive({
  fullname: '',
  shortname: '',
  summary: '',
  categoryid: 0,
  startdate: 0,
  visible: 1,
})

const metaModel = reactive({
  duration_hours: 0,
  level: 'beginner',
  completion_percentage: 100,
  smartmind_code: '',
  sepe_code: '',
  description: '',
  course_category: 0,
})

const objectivesModel = ref<string[]>([])

const saving = ref(false)
const saveError = ref<string | null>(null)

async function fetchData() {
  loading.value = true
  error.value = null
  const result = await call('local_sm_graphics_plugin_get_course_edit_data', { courseid: courseid.value })
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
    Object.assign(form, result.data.core)
    Object.assign(metaModel, result.data.meta)
    objectivesModel.value = (result.data.objectives || []).map((o: any) => o.text)
  }
  loading.value = false
}

async function onSubmit() {
  saving.value = true
  saveError.value = null
  const result = await call('local_sm_graphics_plugin_update_course_full', {
    courseid: courseid.value,
    fullname: form.fullname,
    shortname: form.shortname,
    summary: form.summary,
    categoryid: form.categoryid,
    startdate: form.startdate,
    visible: form.visible,
    duration_hours: metaModel.duration_hours,
    level: metaModel.level,
    completion_percentage: metaModel.completion_percentage,
    smartmind_code: metaModel.smartmind_code,
    sepe_code: metaModel.sepe_code,
    description: metaModel.description,
    course_category: metaModel.course_category,
    objectives_json: JSON.stringify(objectivesModel.value),
    translate: true,
  })
  saving.value = false
  if (result.error) {
    saveError.value = result.error
  } else if (result.data?.success) {
    router.push(`/courses/${result.data.courseid}/landing`)
  }
}

fetchData()
</script>

<style scoped lang="scss">
.smgp-course-editor {
  max-width: 960px;
  margin: 2rem auto;
  padding: 0 1rem;

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
  }
  &__title { font-size: 1.75rem; font-weight: 700; color: #1e293b; margin: 0; }
  &__section { margin-bottom: 1.25rem; }
  &__section-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin: 0 0 0.75rem; }
  &__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    @media (max-width: 600px) { grid-template-columns: 1fr; }
  }
  &__field {
    margin-bottom: 0.75rem;
    label { display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.9rem; }
  }
  &__actions { margin-top: 1.5rem; }
}
</style>
