<template>
  <div class="sm-upload-container">
    <div class="sm-upload-card">
      <div class="text-center mb-4">
        <i class="fa fa-cloud-upload fa-3x text-primary mb-3" />
        <h3 class="mb-1">{{ $t('upload.title') || 'Upload Users' }}</h3>
        <p class="text-muted small">{{ $t('upload.subtitle') || 'Upload a CSV file to create users in bulk.' }}</p>
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="sm-upload-dropzone mb-4" @dragover.prevent @drop.prevent="onDrop">
          <label for="userfile" class="sm-upload-label">
            <i class="fa fa-file-csv fa-2x text-muted mb-2" />
            <span class="d-block fw-bold mb-1">{{ $t('upload.file_label') || 'Choose a CSV file' }}</span>
            <span class="text-muted small">{{ $t('upload.file_help') || 'Format: username, firstname, lastname, email' }}</span>
            <input
              id="userfile"
              type="file"
              accept=".csv"
              required
              class="sm-upload-input"
              @change="onFileChange"
            >
          </label>
        </div>

        <div v-if="selectedFile" class="text-center mb-3 small text-muted">
          <i class="fa fa-file me-1" /> {{ selectedFile.name }}
        </div>

        <div class="d-flex align-items-center gap-3 mb-4">
          <label for="uutype" class="form-label fw-bold mb-0 text-nowrap">
            {{ $t('upload.type_label') || 'Upload type' }}
          </label>
          <select id="uutype" v-model="uutype" class="form-select">
            <option value="0">{{ $t('upload.type_add_new') || 'Add new only' }}</option>
            <option value="1">{{ $t('upload.type_add_update') || 'Add new and update existing' }}</option>
            <option value="2">{{ $t('upload.type_update_only') || 'Update existing only' }}</option>
          </select>
        </div>

        <div class="d-flex gap-2 justify-content-center">
          <NuxtLink to="/management/users" class="btn btn-outline-secondary">
            {{ $t('upload.cancel') || 'Cancel' }}
          </NuxtLink>
          <button type="submit" class="btn btn-primary" :disabled="!selectedFile || uploading">
            <span v-if="uploading" class="spinner-border spinner-border-sm me-1" />
            <i v-else class="fa fa-upload me-1" />
            {{ $t('upload.submit') || 'Upload' }}
          </button>
        </div>
      </form>

      <div v-if="uploadResult" class="alert mt-3" :class="uploadResult.error ? 'alert-danger' : 'alert-success'">
        {{ uploadResult.message }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const authStore = useAuthStore()

const selectedFile = ref<File | null>(null)
const uutype = ref('0')
const uploading = ref(false)
const uploadResult = ref<{ error: boolean; message: string } | null>(null)

const onFileChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  selectedFile.value = file || null
}

const onDrop = (e: DragEvent) => {
  const file = e.dataTransfer?.files?.[0]
  if (file && file.name.endsWith('.csv')) {
    selectedFile.value = file
  }
}

const handleSubmit = async () => {
  if (!selectedFile.value) return

  uploading.value = true
  uploadResult.value = null

  // Redirect to Moodle's upload handler (server-side processing)
  const url = `${authStore.wwwroot}/local/sm_graphics_plugin/pages/uploadusers.php`
  window.location.href = url
}
</script>

<style scoped>
.sm-upload-container {
  width: 100%;
  padding: 2rem 0;
}
.sm-upload-card {
  width: 100%;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  padding: 2.5rem 2rem;
}
.sm-upload-dropzone {
  border: 2px dashed #ced4da;
  border-radius: 8px;
  text-align: center;
  padding: 3.5rem 1rem;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color 0.2s, background 0.2s;
  cursor: pointer;
}
.sm-upload-dropzone:hover,
.sm-upload-dropzone:focus-within {
  border-color: var(--bs-primary, #10b981);
  background: #f0f7ff;
}
.sm-upload-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  margin: 0;
}
.sm-upload-input {
  display: block;
  margin-top: 0.75rem;
  font-size: 0.85rem;
}
.sm-upload-input::file-selector-button {
  border: 1px solid #ced4da;
  border-radius: 4px;
  padding: 0.25rem 0.75rem;
  background: #f8fafc;
  cursor: pointer;
}
</style>
