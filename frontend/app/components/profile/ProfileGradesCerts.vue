<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else id="smgp-grades-certificates" class="smgp-gradescerts">
    <div v-if="data?.hascourses" class="smgp-gradescerts__grid">
      <div
        v-for="course in data.courses" :key="course.courseid"
        class="smgp-gradescerts__card"
        :class="{ 'smgp-gradescerts__card--pending': !course.hascertificate }"
      >
        <div class="smgp-gradescerts__card-top">
          <img v-if="course.hascourseimage" :src="course.courseimage" :alt="course.coursename" class="smgp-gradescerts__card-img">
          <div v-else class="smgp-gradescerts__card-placeholder"><i class="fa fa-graduation-cap" /></div>
          <div class="smgp-gradescerts__star">
            <i class="icon-star" />
          </div>
          <span
            class="smgp-gradescerts__badge"
            :class="course.hascertificate ? 'smgp-gradescerts__badge--complete' : 'smgp-gradescerts__badge--pending'"
          >
            {{ course.hascertificate ? $t('certificates.official') : $t('certificates.inprogress') }}
          </span>
        </div>

        <div class="smgp-gradescerts__card-body">
          <h3 class="smgp-gradescerts__course-name">{{ course.coursename }}</h3>

          <template v-if="course.hascertificate">
            <p class="smgp-gradescerts__date">{{ $t('certificates.issued', { date: course.certdate }) }}</p>
            <p v-if="course.certcode" class="smgp-gradescerts__code">ID: {{ course.certcode }}</p>
            <a
              :href="course.downloadurl"
              class="smgp-gradescerts__btn"
              data-download-cert="1"
              target="_blank"
            >
              {{ $t('certificates.download') }}
            </a>
          </template>

          <template v-else>
            <p class="smgp-gradescerts__date smgp-gradescerts__date--pending">
              {{ $t('certificates.completed', { progress: course.progress }) }}
            </p>
            <div class="smgp-gradescerts__progress">
              <div class="smgp-gradescerts__progress-bar">
                <div class="smgp-gradescerts__progress-fill" :style="{ width: course.progress + '%' }" />
              </div>
              <span class="smgp-gradescerts__progress-text">{{ course.progress }}%</span>
            </div>
            <span class="smgp-gradescerts__btn smgp-gradescerts__btn--disabled">
              {{ $t('certificates.unavailable') }}
            </span>
          </template>
        </div>
      </div>
    </div>

    <div v-else class="smgp-gradescerts__empty">
      <p>{{ $t('certificates.empty') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
const { getGradesCertificates } = useCourseApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

getGradesCertificates().then((result) => {
  loading.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
})
</script>
