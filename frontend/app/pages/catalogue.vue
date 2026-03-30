<template>
  <div>
    <div class="smartmind-catalogue-header mb-3">
      <span class="smartmind-catalogue-header__eyebrow">SmartMind</span>
      <h1 class="smartmind-catalogue-header__title">{{ $t('nav.catalogue') }}</h1>
      <p class="smartmind-catalogue-header__desc">Explore all available courses</p>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status" />
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else>
      <!-- Category filter -->
      <div v-if="data?.hascategories" class="smartmind-catalogue-filters mb-4">
        <div class="smartmind-catalogue-filters__badges">
          <button class="smartmind-badge" :class="{ 'smartmind-badge--active': selectedCategory === 0 }" @click="filterByCategory(0)">
            All
          </button>
          <button
            v-for="cat in data.categories" :key="cat.id"
            class="smartmind-badge" :class="{ 'smartmind-badge--active': selectedCategory === cat.id }"
            @click="filterByCategory(cat.id)"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>

      <div v-if="!data?.hascourses" class="catalog-section__empty">No courses available.</div>

      <div class="catalogue-grid">
        <NuxtLink v-for="course in data?.courses" :key="course.id" :to="`/courses/${course.id}/landing`" class="catalogue-card">
          <div class="catalogue-card__header">
            <img v-if="course.image" :src="course.image" :alt="course.fullname" class="catalogue-card__icon">
            <div v-else class="catalogue-card__icon-placeholder">
              <i class="bi bi-book" />
            </div>
            <span v-if="course.categoryname" class="catalogue-card__category-badge">{{ course.categoryname }}</span>
          </div>
          <div class="catalogue-card__body">
            <h3 class="catalogue-card__title">{{ course.fullname }}</h3>
            <div class="catalogue-card__meta">
              <span class="catalogue-card__tag">
                <i class="bi bi-signal" /> {{ course.level }}
              </span>
              <span v-if="course.duration_hours > 0" class="catalogue-card__tag">
                <i class="bi bi-clock" /> {{ course.duration_hours }}h
              </span>
              <span v-if="course.isenrolled" class="catalogue-card__tag" style="color: #10b981">
                <i class="bi bi-check-circle" /> Enrolled
              </span>
            </div>
          </div>
        </NuxtLink>
      </div>

      <p class="text-muted mt-3 small">{{ data?.totalcount }} courses</p>
    </template>
  </div>
</template>

<script setup lang="ts">
const { getCatalogue } = useCourseApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const selectedCategory = ref(0)

const fetchData = async (categoryid: number = 0) => {
  loading.value = true
  const result = await getCatalogue(categoryid)
  loading.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
}

const filterByCategory = (categoryid: number) => {
  selectedCategory.value = categoryid
  fetchData(categoryid)
}

onMounted(() => fetchData())
</script>
