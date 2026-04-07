<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else id="smgp-grades-certificates" class="smgp-gradescerts">

    <div class="smartmind-catalogue-header mb-4">
      <span class="smartmind-catalogue-header__eyebrow">UNLIMITED LEARNING</span>
      <h1 class="smartmind-catalogue-header__title">{{ $t('nav.grades') }}</h1>
      <p class="smartmind-catalogue-header__desc">Your grades and certificates</p>
    </div>

    <div v-if="data?.hascourses" class="smgp-gradescerts__grid">
      <div v-for="course in data.courses" :key="course.courseid" class="smgp-gradescerts__card">
        <div
          class="smgp-gradescerts__card-top"
          :class="{ 'smgp-gradescerts__card-top--pending': !course.hascertificate }"
        >
          <div class="smgp-gradescerts__star">
            <i class="fa fa-star" />
          </div>
          <span class="smgp-gradescerts__badge">
            {{ course.hascertificate ? 'Official certificate' : 'In progress' }}
          </span>
        </div>

        <div class="smgp-gradescerts__card-body">
          <h3 class="smgp-gradescerts__course-name">{{ course.coursename }}</h3>

          <!-- Completed: show date + code + download -->
          <template v-if="course.hascertificate">
            <p class="smgp-gradescerts__date">Issued {{ course.certdate }}</p>
            <p v-if="course.certcode" class="smgp-gradescerts__code">ID: {{ course.certcode }}</p>
            <a
              :href="course.downloadurl"
              class="smgp-gradescerts__download-btn"
              data-download-cert="1"
              target="_blank"
            >
              Download PDF
            </a>
          </template>

          <!-- In progress: show progress + not available -->
          <template v-else>
            <p class="smgp-gradescerts__date">{{ course.progress }}% completed</p>
            <div class="smgp-gradescerts__progress-bar">
              <div class="smgp-gradescerts__progress-fill" :style="{ width: course.progress + '%' }" />
            </div>
            <span class="smgp-gradescerts__download-btn smgp-gradescerts__download-btn--disabled">
              Not yet available
            </span>
          </template>
        </div>
      </div>
    </div>

    <div v-else class="smgp-gradescerts__empty">
      <p>No courses with grades yet.</p>
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
