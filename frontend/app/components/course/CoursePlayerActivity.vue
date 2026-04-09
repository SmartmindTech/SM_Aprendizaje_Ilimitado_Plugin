<template>
  <!-- Loading -->
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status" />
  </div>

  <!-- Inline render: chrome composed client-side from structured `inline` payload. -->
  <template v-else-if="render === 'inline' && inline">
    <!-- mod_page: full body (Moodle-formatted user content) -->
    <div
      v-if="inline.kind === 'page'"
      class="smgp-activity-content smgp-activity-content--page"
      v-html="inline.content"
    />

    <!-- mod_book: chapter title + counter + body -->
    <div
      v-else-if="inline.kind === 'book'"
      class="smgp-activity-content smgp-activity-content--book"
    >
      <p v-if="inline.empty">{{ $t('course_page.no_chapters') }}</p>
      <template v-else>
        <div class="smgp-activity-content__chapter-info">
          <strong>{{ inline.chapter?.title }}</strong>
          <span class="text-muted">
            ({{ inline.chapter?.current }}/{{ inline.chapter?.total }})
          </span>
        </div>
        <div v-html="inline.content" />
      </template>
    </div>

    <!-- mod_resource: intro + file preview + (conditional) download -->
    <div
      v-else-if="inline.kind === 'resource'"
      class="smgp-activity-content smgp-activity-content--resource"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <template v-if="inline.file">
        <img
          v-if="inline.file.kind === 'image'"
          :src="inline.file.url"
          :alt="inline.file.name"
          class="smgp-activity-content__image"
        >

        <iframe
          v-else-if="inline.file.kind === 'pdf'"
          :src="`${inline.file.url}#toolbar=1&navpanes=0`"
          class="smgp-activity-content__document-frame"
          :title="inline.file.name"
        />

        <iframe
          v-else-if="inline.file.kind === 'document'"
          :src="`https://docs.google.com/gview?url=${encodeURIComponent(inline.file.url)}&embedded=true`"
          class="smgp-activity-content__document-frame"
          :title="inline.file.name"
        />

        <video
          v-else-if="inline.file.kind === 'video'"
          controls
          preload="metadata"
          class="smgp-activity-content__video-player"
        >
          <source :src="inline.file.url" :type="inline.file.mimetype">
          {{ $t('course_page.video_unsupported') }}
        </video>

        <audio
          v-else-if="inline.file.kind === 'audio'"
          controls
          preload="metadata"
          class="smgp-activity-content__audio-player"
        >
          <source :src="inline.file.url" :type="inline.file.mimetype">
        </audio>

        <a
          v-else
          :href="inline.file.url"
          class="smgp-activity-content__file btn btn-primary btn-sm"
        >
          <i class="icon-download" />
          {{ $t('course_page.download') }} {{ inline.file.name }} ({{ inline.file.size }})
        </a>
      </template>
    </div>

    <!-- mod_label: just the formatted intro -->
    <div
      v-else-if="inline.kind === 'label'"
      class="smgp-activity-content smgp-activity-content--label"
      v-html="inline.content"
    />

    <!-- Fallback: activity type the backend can't render inline -->
    <div v-else class="smgp-activity-content">
      <p>{{ $t('course_page.content_not_available') }}</p>
    </div>
  </template>

  <!-- Iframe (SCORM, quiz, embed) -->
  <iframe
    v-else-if="render === 'iframe' && iframeUrl"
    :src="iframeUrl"
    allow="fullscreen; autoplay; encrypted-media"
    allowfullscreen
  />

  <!-- Redirect (open in new tab) -->
  <div v-else-if="render === 'redirect' && redirectUrl" class="smgp-course-content__redirect">
    <i class="icon-external-link" />
    <p>{{ $t('course_page.redirect_message') }}</p>
    <a :href="redirectUrl" class="btn btn-success" target="_blank" rel="noopener">
      {{ $t('course_page.open_activity') }}
    </a>
  </div>

  <!-- Placeholder (no activity selected) -->
  <div v-else class="smgp-course-content__placeholder">
    <i class="bi bi-collection-play" />
    <p>{{ $t('course_page.select_activity') }}</p>
  </div>
</template>

<script setup lang="ts">
import type { InlineData, ActivityRender } from '~/types/coursePlayer'

defineProps<{
  loading: boolean
  render: ActivityRender
  inline: InlineData | null
  iframeUrl: string | null
  redirectUrl?: string | null
}>()
</script>
