<template>
  <div class="smgp-mgmt-page">
    <header class="smgp-mgmt-page__header">
      <h1 class="smgp-mgmt-page__title">{{ $t('management.upload.title') }}</h1>
      <p class="smgp-mgmt-page__desc">{{ $t('management.upload.desc') }}</p>
    </header>

    <!-- Sample CSV download card -->
    <div class="smgp-mgmt-grid mb-3">
      <button type="button" class="smgp-mgmt-card" @click="downloadSampleCsv">
        <span class="smgp-mgmt-card__icon"><i class="icon-download" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.upload.downloadSample') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.upload.downloadSampleDesc') }}</span>
        </span>
      </button>
    </div>

    <!-- Upload form -->
    <div class="sm-upload-card">
      <form @submit.prevent="handleSubmit">
        <div class="sm-upload-dropzone mb-4" @dragover.prevent @drop.prevent="onDrop">
          <label for="userfile" class="sm-upload-label">
            <i class="icon-file-text mb-2" style="font-size: 2rem;" />
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
          <i class="icon-file me-1" /> {{ selectedFile.name }}
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
            <i v-else class="icon-upload me-1" />
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

// Generate a CSV sample client-side and trigger a browser download.
// Columns mirror the existing Excel template at plantilla_usuarios.php
// (username, firstname, lastname, email) and we ship two example rows
// the user can replace.
const downloadSampleCsv = () => {
  const rows = [
    ['username', 'firstname', 'lastname', 'email'],
    ['juan.garcia',     'Juan',  'Garcia Lopez',     'juan.garcia@ejemplo.com'],
    ['maria.rodriguez', 'Maria', 'Rodriguez Perez',  'maria.rodriguez@ejemplo.com'],
  ]
  // Quote every cell defensively in case names contain commas or quotes.
  const csv = rows
    .map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\r\n')
  // Prepend BOM so Excel opens UTF-8 with accents intact.
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'plantilla_usuarios.csv'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
</script>

<style scoped>
.sm-upload-card {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 14px;
  padding: 2rem;
  max-width: 720px;
  margin: 1.25rem auto 0;
}
.sm-upload-dropzone {
  border: 2px dashed #ced4da;
  border-radius: 8px;
  text-align: center;
  padding: 3rem 1rem;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color 0.2s, background 0.2s;
  cursor: pointer;
}
.sm-upload-dropzone:hover,
.sm-upload-dropzone:focus-within {
  border-color: #10b981;
  background: rgba(16, 185, 129, 0.04);
}
.sm-upload-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  margin: 0;
  color: #475569;
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

/* The download-sample button is rendered with the smgp-mgmt-card markup,
   but it's a <button>, so neutralize the UA defaults that would show as
   text-align:center, border, etc. */
button.smgp-mgmt-card {
  text-align: left;
  font: inherit;
  cursor: pointer;
  width: 100%;
}
</style>
