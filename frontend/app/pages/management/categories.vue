<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <template v-else-if="data">
    <!-- Category list view -->
    <div v-if="!showCreateForm" class="smgp-mgmt-page">
      <header class="smgp-mgmt-page__header d-flex align-items-start gap-3">
        <NuxtLink to="/management/users" class="btn btn-outline-secondary mt-1">
          <i class="icon-arrow-left" />
        </NuxtLink>
        <div class="flex-grow-1">
          <h1 class="smgp-mgmt-page__title">{{ $t('management.categories.title') }}</h1>
          <p class="smgp-mgmt-page__desc">{{ $t('management.categories.desc') }}</p>
        </div>
        <button class="btn btn-primary" @click="showCreateForm = true">
          <i class="icon-plus me-1" />
          {{ $t('categories.create') || 'Create category' }}
        </button>
      </header>

      <template v-if="data.hascategories">
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle sm-catmgmt-table">
            <thead class="table-light">
              <tr>
                <th style="width: 80px;">{{ $t('categories.preview') || 'Preview' }}</th>
                <th>{{ $t('categories.name') || 'Name' }}</th>
                <th class="text-center" style="width: 140px;">{{ $t('categories.actions') || 'Actions' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in data.categories" :key="cat.id">
                <td>
                  <div
                    v-if="cat.image_src"
                    class="sm-catmgmt-thumb"
                    :style="{ backgroundImage: `url('${cat.image_src}')` }"
                  />
                  <div v-else class="sm-catmgmt-thumb sm-catmgmt-thumb--empty">
                    <i class="fa fa-image text-muted" />
                  </div>
                </td>
                <td class="fw-semibold">{{ cat.name }}</td>
                <td class="text-center text-nowrap">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary me-1 sm-edit-cat-btn"
                    @click="openEditModal(cat)"
                  >
                    <i class="fa fa-pen" />
                    {{ $t('categories.edit') || 'Edit' }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    @click="confirmDeleteCategory(cat)"
                  >
                    <i class="fa fa-trash-can" />
                    {{ $t('categories.delete') || 'Delete' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div v-else class="alert alert-info">
        {{ data.nocategories || 'No categories found.' }}
      </div>
    </div>

    <!-- Create category form -->
    <div v-else class="sm-createcategory p-4 w-100">
      <h2 class="mb-4">{{ $t('categories.create_heading') || 'Create Category' }}</h2>
      <div class="row">
        <!-- Form column -->
        <div class="col-lg-7">
          <form @submit.prevent="submitCreate">
            <div class="mb-3">
              <label for="category_name" class="form-label fw-bold">
                {{ $t('categories.name_label') || 'Name' }}
                <span class="text-danger">*</span>
              </label>
              <input
                id="category_name"
                v-model="createForm.name"
                type="text"
                class="form-control"
                required
                maxlength="255"
                autocomplete="off"
              >
            </div>
            <div class="mb-3">
              <label for="category_image" class="form-label fw-bold">
                {{ $t('categories.image_label') || 'Image' }}
              </label>
              <input
                id="category_image"
                type="file"
                class="form-control"
                accept="image/jpeg,image/png,image/webp"
                @change="onCreateImageChange"
              >
              <div class="form-text">{{ $t('categories.image_help') || 'JPEG, PNG or WebP. Max 2MB.' }}</div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary" :disabled="!createForm.name.trim()">
                <i class="fa fa-check me-1" />
                {{ $t('categories.submit') || 'Create' }}
              </button>
              <button type="button" class="btn btn-outline-secondary" @click="showCreateForm = false">
                {{ $t('categories.cancel') || 'Cancel' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Live preview column -->
        <div class="col-lg-5 mt-4 mt-lg-0">
          <label class="form-label fw-bold d-block mb-2">{{ $t('categories.preview') || 'Preview' }}</label>
          <div class="sm-createcat-preview-wrapper">
            <div class="sm-createcat-preview">
              <article class="category-card card">
                <div
                  class="category-card__image"
                  :style="createPreviewImage ? { backgroundImage: `url('${createPreviewImage}')` } : {}"
                >
                  <div class="category-card__overlay">
                    <h3 class="category-card__title">{{ createForm.name }}</h3>
                  </div>
                </div>
              </article>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit modal -->
    <div v-if="showEditModal" class="modal fade show" style="display:block" tabindex="-1">
      <div class="modal-backdrop fade show" @click="showEditModal = false" />
      <div class="modal-dialog modal-lg" style="position:relative;z-index:1">
        <div class="modal-content">
          <form @submit.prevent="submitEdit">
            <div class="modal-header">
              <h5 class="modal-title">{{ $t('categories.edit') || 'Edit' }}</h5>
              <button type="button" class="btn-close" @click="showEditModal = false" />
            </div>
            <div class="modal-body">
              <div class="row">
                <!-- Form fields -->
                <div class="col-md-7">
                  <div class="mb-3">
                    <label for="sm-edit-cat-name" class="form-label fw-bold">
                      {{ $t('categories.name_label') || 'Name' }}
                      <span class="text-danger">*</span>
                    </label>
                    <input
                      id="sm-edit-cat-name"
                      v-model="editForm.name"
                      type="text"
                      class="form-control"
                      required
                      maxlength="255"
                    >
                  </div>
                  <div class="mb-3">
                    <label for="sm-edit-cat-image" class="form-label fw-bold">
                      {{ $t('categories.image_label') || 'Image' }}
                    </label>
                    <input
                      id="sm-edit-cat-image"
                      type="file"
                      class="form-control"
                      accept="image/jpeg,image/png,image/webp"
                      @change="onEditImageChange"
                    >
                    <div class="form-text">{{ $t('categories.image_help') || 'JPEG, PNG or WebP. Max 2MB.' }}</div>
                  </div>
                </div>
                <!-- Live preview -->
                <div class="col-md-5">
                  <label class="form-label fw-bold d-block mb-2">{{ $t('categories.preview') || 'Preview' }}</label>
                  <div class="sm-createcat-preview-wrapper">
                    <article class="category-card card">
                      <div
                        class="category-card__image"
                        :style="editPreviewImage ? { backgroundImage: `url('${editPreviewImage}')` } : {}"
                      >
                        <div class="category-card__overlay">
                          <h3 class="category-card__title">{{ editForm.name }}</h3>
                        </div>
                      </div>
                    </article>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" @click="showEditModal = false">
                {{ $t('categories.cancel') || 'Cancel' }}
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="fa fa-check me-1" />
                {{ $t('categories.save') || 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { getCategoriesList, deleteCategory } = useManagementApi()
const { call } = useMoodleAjax()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

const showCreateForm = ref(false)
const showEditModal = ref(false)

const createForm = ref({ name: '' })
const createPreviewImage = ref<string | null>(null)
const createImageFile = ref<File | null>(null)

const editForm = ref({ id: 0, name: '' })
const editPreviewImage = ref<string | null>(null)
const editImageFile = ref<File | null>(null)

const fetchData = async () => {
  loading.value = true
  const result = await getCategoriesList()
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
}

const onCreateImageChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  createImageFile.value = file
  const reader = new FileReader()
  reader.onload = (ev) => {
    createPreviewImage.value = ev.target?.result as string
  }
  reader.readAsDataURL(file)
}

const onEditImageChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  editImageFile.value = file
  const reader = new FileReader()
  reader.onload = (ev) => {
    editPreviewImage.value = ev.target?.result as string
  }
  reader.readAsDataURL(file)
}

const submitCreate = async () => {
  // TODO: wire to actual create API when available
  await call('local_sm_graphics_plugin_create_category', { name: createForm.value.name })
  showCreateForm.value = false
  createForm.value = { name: '' }
  createPreviewImage.value = null
  await fetchData()
}

const openEditModal = (cat: any) => {
  editForm.value = { id: cat.id, name: cat.name }
  editPreviewImage.value = cat.image_src || null
  editImageFile.value = null
  showEditModal.value = true
}

const submitEdit = async () => {
  // TODO: wire to actual update API when available
  await call('local_sm_graphics_plugin_update_category', {
    categoryid: editForm.value.id,
    name: editForm.value.name,
  })
  showEditModal.value = false
  await fetchData()
}

const confirmDeleteCategory = async (cat: any) => {
  if (!confirm(`Delete category "${cat.name}"?`)) return
  await deleteCategory(cat.id)
  await fetchData()
}

fetchData()
</script>

<style scoped>
.sm-catmgmt-thumb {
  width: 64px;
  height: 40px;
  border-radius: 4px;
  background-size: cover;
  background-position: center;
}
.sm-catmgmt-thumb--empty {
  background-color: #e9ecef;
  display: flex;
  align-items: center;
  justify-content: center;
}
.sm-catmgmt-table th {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #64748b;
}

.sm-createcat-preview-wrapper {
  max-width: 500px;
}
.sm-createcat-preview .category-card,
.category-card {
  padding: 0;
  overflow: hidden;
  border: none;
}
.category-card__image {
  width: 100%;
  height: 14rem;
  background-size: cover;
  background-position: center;
  background-color: #374151;
}
.category-card__overlay {
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: end;
  padding: 1rem;
}
.category-card__title {
  font-size: 1rem;
  color: #fff;
  margin: 0;
  text-transform: uppercase;
  min-height: 1.2em;
}
</style>
