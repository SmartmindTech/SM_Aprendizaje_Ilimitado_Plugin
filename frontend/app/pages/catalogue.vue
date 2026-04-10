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
        <span v-if="type.icon" class="smartmind-type-badge__icon">{{ type.icon }}</span>
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
                <i class="fa fa-clock-o" /> {{ course.duration_hours }}h
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
  { id: 'curso',          label: 'Curso',                       icon: '📚' },
  { id: 'pildora',        label: 'Píldora',                     icon: '💊' },
  { id: 'video',          label: 'Video',                       icon: '🎬' },
  { id: 'ruta',           label: 'Ruta',                        icon: '🗺️' },
  { id: 'actividad',      label: 'Actividad',                   icon: '🎯' },
  { id: 'microcredencial', label: 'Microcredencial',            icon: '🏅' },
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
