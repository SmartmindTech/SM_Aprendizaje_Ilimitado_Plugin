<template>
  <div>
    <!-- Header -->
    <div class="smartmind-catalogue-header mb-3">
      <h1 class="smartmind-catalogue-header__title">{{ $t('nav.catalogue') }}</h1>
      <p class="smartmind-catalogue-header__desc">{{ $t('catalogue.description') }}</p>
    </div>

    <!-- Search bar -->
    <div class="smartmind-catalogue-search mb-3">
      <div class="smartmind-catalogue-search__wrapper">
        <i class="icon-search smartmind-catalogue-search__icon" />
        <input
          v-model="searchTerm"
          type="text"
          class="smartmind-catalogue-search__input"
          :placeholder="$t('catalogue.searchPlaceholder')"
          autocomplete="off"
        >
      </div>
    </div>

    <!-- Type filter row -->
    <div class="smartmind-catalogue-types mb-5">
      <button
        v-for="type in typeFilters"
        :key="type.id"
        class="smartmind-type-badge"
        :class="{ 'smartmind-type-badge--active': selectedType === type.id }"
        @click="selectedType = type.id"
      >
        <span v-if="type.icon" class="smartmind-type-badge__icon" :class="{ 'smartmind-type-badge__icon--active': selectedType === type.id }"><span class="svg-icon" v-html="type.icon"></span></span>
        {{ type.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status" />
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else>
      <!-- Category filter badges -->
      <div v-if="data?.hascategories" class="smartmind-catalogue-categories mb-3">
        <button
          class="smartmind-cat-badge"
          :class="{ 'smartmind-cat-badge--active': selectedCategory === 0 }"
          @click="selectedCategory = 0"
        >
          {{ $t('catalogue.all') }}
        </button>
        <button
          v-for="cat in data.categories"
          :key="cat.id"
          class="smartmind-cat-badge"
          :class="{ 'smartmind-cat-badge--active': selectedCategory === cat.id }"
          @click="selectedCategory = cat.id"
        >
          {{ cat.name }}
        </button>
      </div>

      <!-- Result count -->
      <!--borrado-->



      <!-- Course grid -->
      <div v-if="visibleCourses.length === 0" class="catalog-section__empty">
        {{ $t('catalogue.noResults') }}
      </div>

      <div v-else class="smartmind-catalogue-grid">
        <NuxtLink
          v-for="course in visibleCourses"
          :key="course.id"
          :to="`/courses/${course.id}/landing`"
          class="catalogue-card"
          :data-category="course.categoryname?.toLowerCase()"
        >
          <div class="catalogue-card__image-wrap">
            <img
              v-if="course.image"
              :src="course.image"
              :alt="course.fullname"
              class="catalogue-card__image"
            >
            <div v-else class="catalogue-card__image-placeholder">
              <i class="fa fa-graduation-cap" />
            </div>
            <span v-if="course.isnew" class="catalogue-card__badge">{{ $t('catalogue.new') }}</span>
          </div>
          <div class="catalogue-card__body">
            <div v-if="course.categoryname" class="catalogue-card__category">{{ course.categoryname }}</div>
            <h3 class="catalogue-card__title">{{ course.fullname }}</h3>
            <div v-if="course.teachername" class="catalogue-card__author">{{ course.teachername }}</div>
            <div class="catalogue-card__meta">
              <span v-if="course.duration_hours" class="catalogue-card__meta-item">
                <i class="fa fa-clock-o" /> {{ course.duration_hours }}
              </span>
              <span v-if="course.activitycount" class="catalogue-card__meta-item">
                <i class="fa fa-list" /> {{ course.activitycount }}
              </span>
              <span v-if="course.isenrolled" class="catalogue-card__meta-item catalogue-card__meta-item--enrolled">
                <i class="fa fa-check-circle" /> {{ $t('catalogue.enrolled') }}
              </span>
            </div>
          </div>
        </NuxtLink>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useCatalogueStore } from '~/stores/catalogue'
import type { CatalogueCourse } from '~/types/catalogue'

const catalogueStore = useCatalogueStore()
const { t } = useI18n()

const { loading, error, data, courses } = storeToRefs(catalogueStore)

// Fetch from cache or API.
catalogueStore.fetch()

// ── Local filter state ──
const selectedCategory = ref(0)
const selectedType = ref('all')
const searchTerm = ref('')

const typeFilters = computed(() => [
  { id: 'all',            label: t('catalogue.all'),            icon: '' },
  { id: 'curso',          label: 'Curso',                        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-library-big-icon lucide-library-big"><rect width="8" height="18" x="3" y="3" rx="1"/><path d="M7 3v18"/><path d="M20.4 18.9c.2.5-.1 1.1-.6 1.3l-1.9.7c-.5.2-1.1-.1-1.3-.6L11.1 5.1c-.2-.5.1-1.1.6-1.3l1.9-.7c.5-.2 1.1.1 1.3.6Z"/></svg>' },
  { id: 'pildora',        label: 'Píldora',                     icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pill-icon lucide-pill"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>' },
  { id: 'video',          label: 'Video',                       icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clapperboard-icon lucide-clapperboard"><path d="m12.296 3.464 3.02 3.956"/><path d="M20.2 6 3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3z"/><path d="M3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="m6.18 5.276 3.1 3.899"/></svg>' },
  { id: 'ruta',           label: 'Ruta',                        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-icon lucide-map"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/></svg>' },
  { id: 'actividad',      label: 'Actividad',                   icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-target-icon lucide-target"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>' },
  { id: 'microcredencial',label: 'Microcredencial',            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-medal-icon lucide-medal"><path d="M7.21 15 2.66 7.14a2 2 0 0 1 .13-2.2L4.4 2.8A2 2 0 0 1 6 2h12a2 2 0 0 1 1.6.8l1.6 2.14a2 2 0 0 1 .14 2.2L16.79 15"/><path d="M11 12 5.12 2.2"/><path d="m13 12 5.88-9.8"/><path d="M8 7h8"/><circle cx="12" cy="17" r="5"/><path d="M12 18v-2h-.5"/></svg>' },
])

const visibleCourses = computed<CatalogueCourse[]>(() => {
  const all = courses.value
  const term = searchTerm.value.trim().toLowerCase()
  return all.filter((c) => {
    if (selectedCategory.value !== 0 && c.categoryid !== selectedCategory.value) return false
    if (term && !c.fullname.toLowerCase().includes(term)) return false
    return true
  })
})
</script>
