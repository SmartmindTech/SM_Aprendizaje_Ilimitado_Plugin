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

    <!-- mod_book: chapter title + counter + body + navigation arrows -->
    <div
      v-else-if="inline.kind === 'book'"
      class="smgp-activity-content smgp-activity-content--book"
    >
      <p v-if="inline.empty">{{ $t('course_page.no_chapters') }}</p>
      <template v-else>
        <div class="smgp-activity-content__chapter-header">
          <button
            type="button"
            class="smgp-book-nav__btn"
            :disabled="bookCurrentChapter <= 1"
            @click="navigateBookChapter(bookCurrentChapter - 1)"
          >
            <i class="bi bi-chevron-left" />
          </button>
          <div class="smgp-activity-content__chapter-info">
            <strong>{{ bookDisplayTitle }}</strong>
            <span class="text-muted small">
              {{ bookCurrentChapter }} / {{ bookTotalChapters }}
            </span>
          </div>
          <button
            type="button"
            class="smgp-book-nav__btn"
            :disabled="bookCurrentChapter >= bookTotalChapters"
            @click="navigateBookChapter(bookCurrentChapter + 1)"
          >
            <i class="bi bi-chevron-right" />
          </button>
        </div>
        <div class="smgp-activity-content__chapter-body" v-html="bookDisplayContent" />
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

    <!-- mod_url: external link or embeddable content (YouTube, Vimeo, Genially) -->
    <div
      v-else-if="inline.kind === 'url'"
      class="smgp-activity-content smgp-activity-content--url"
    >
      <!-- Embeddable URL (YouTube, Vimeo, Genially) -->
      <iframe
        v-if="inline.urlkind === 'embed' && inline.embedurl"
        :src="inline.embedurl"
        class="smgp-activity-content__embed-frame"
        allow="fullscreen; autoplay; encrypted-media"
        allowfullscreen
      />

      <!-- Non-embed URL: intro text + centered link card -->
      <template v-else>
        <div
          v-if="inline.intro"
          class="smgp-activity-content__url-intro"
          v-html="inline.intro"
        />
        <div class="smgp-activity-content__url-card">
          <div class="smgp-activity-content__url-card-icon">
            <i class="bi bi-link-45deg" />
          </div>
          <div class="smgp-activity-content__url-card-body">
            <h4>{{ inline.name || 'External link' }}</h4>
            <p>{{ inline.url }}</p>
            <a :href="inline.url" target="_blank" rel="noopener" class="btn btn-success btn-sm">
              <i class="bi bi-box-arrow-up-right me-1" />
              {{ $t('course_page.open_link') || 'Open link' }}
            </a>
          </div>
        </div>
      </template>
    </div>

    <!-- mod_glossary: searchable list of term definitions -->
    <div
      v-else-if="inline.kind === 'glossary'"
      class="smgp-activity-content smgp-activity-content--glossary"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <input
        v-if="inline.entries && inline.entries.length > 5"
        v-model="glossarySearch"
        type="text"
        class="smgp-glossary__search"
        :placeholder="$t('course_page.glossary_search')"
      >

      <div
        v-for="entry in filteredGlossaryEntries"
        :key="entry.id"
        class="smgp-glossary__entry"
      >
        <h4 class="smgp-glossary__term">{{ entry.concept }}</h4>
        <div class="smgp-glossary__definition" v-html="entry.definition" />
      </div>

      <p v-if="inline.entries && inline.entries.length === 0" class="smgp-glossary__empty">
        {{ $t('course_page.glossary_empty') }}
      </p>
    </div>

    <!-- mod_folder: file browser with download links -->
    <div
      v-else-if="inline.kind === 'folder'"
      class="smgp-activity-content smgp-activity-content--folder"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <p v-if="!inline.files || inline.files.length === 0" class="smgp-folder__empty">
        {{ $t('course_page.folder_empty') }}
      </p>

      <div v-else class="smgp-folder__list">
        <a
          v-for="file in inline.files"
          :key="file.url"
          :href="file.url"
          class="smgp-folder__file"
          target="_blank"
          rel="noopener"
        >
          <i :class="file.icon" class="smgp-folder__icon" />
          <div class="smgp-folder__info">
            <span class="smgp-folder__name">{{ file.name }}</span>
            <span v-if="file.path" class="smgp-folder__path">{{ file.path }}/</span>
          </div>
          <span class="smgp-folder__size">{{ file.size }}</span>
          <i class="bi bi-download smgp-folder__download" />
        </a>
      </div>
    </div>

    <!-- mod_choice: poll with options + results -->
    <div
      v-else-if="inline.kind === 'choice'"
      class="smgp-activity-content smgp-activity-content--choice"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <p v-if="inline.isclosed" class="smgp-choice__closed">
        {{ $t('course_page.choice_closed') }}
      </p>

      <template v-if="!inline.isclosed && (!inline.hasanswered || inline.allowupdate)">
        <div class="smgp-choice__options">
          <label
            v-for="opt in inline.options"
            :key="opt.id"
            class="smgp-choice__option"
            :class="{ 'smgp-choice__option--selected': choiceSelected.includes(opt.id) }"
          >
            <input
              v-if="inline.allowmultiple"
              v-model="choiceSelected"
              type="checkbox"
              :value="opt.id"
            >
            <input
              v-else
              v-model="choiceSingleSelected"
              type="radio"
              name="choice"
              :value="opt.id"
            >
            <span v-html="opt.text" />
          </label>
        </div>
        <button
          class="btn btn-success btn-sm mt-3"
          :disabled="choiceSubmitting || (inline.allowmultiple ? choiceSelected.length === 0 : !choiceSingleSelected)"
          @click="submitChoice"
        >
          {{ inline.hasanswered ? $t('course_page.choice_update') : $t('course_page.choice_submit') }}
        </button>
      </template>

      <p v-else-if="inline.hasanswered && !inline.allowupdate" class="smgp-choice__answered">
        {{ $t('course_page.choice_already_answered') }}
      </p>

      <!-- Results bar chart -->
      <div v-if="inline.results" class="smgp-choice__results">
        <h4>{{ $t('course_page.choice_results') }}</h4>
        <div
          v-for="r in inline.results"
          :key="r.optionid"
          class="smgp-choice__result-row"
        >
          <span class="smgp-choice__result-label" v-html="r.text" />
          <div class="smgp-choice__result-bar-wrap">
            <div
              class="smgp-choice__result-bar"
              :style="{ width: choiceBarWidth(r.count) + '%' }"
            />
          </div>
          <span class="smgp-choice__result-count">{{ r.count }}</span>
        </div>
      </div>
    </div>

    <!-- mod_survey: predefined questionnaire -->
    <div
      v-else-if="inline.kind === 'survey'"
      class="smgp-activity-content smgp-activity-content--survey"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <p v-if="inline.done" class="smgp-survey__completed">
        {{ $t('course_page.survey_completed') }}
      </p>

      <template v-else>
        <div
          v-for="q in inline.questions"
          :key="q.id"
          class="smgp-survey__question"
        >
          <label class="smgp-survey__label">{{ q.text }}</label>
          <div v-if="q.options" class="smgp-survey__options">
            <label
              v-for="(opt, idx) in q.options.split(',')"
              :key="idx"
              class="smgp-survey__option"
            >
              <input
                v-model="surveyAnswers[q.id]"
                type="radio"
                :name="'survey_' + String(q.id)"
                :value="String(Number(idx) + 1)"
              >
              <span>{{ opt.trim() }}</span>
            </label>
          </div>
        </div>
        <button
          class="btn btn-success btn-sm mt-3"
          :disabled="surveySubmitting"
          @click="submitSurvey"
        >
          {{ $t('course_page.survey_submit') }}
        </button>
      </template>
    </div>

    <!-- mod_feedback: multi-page form -->
    <div
      v-else-if="inline.kind === 'feedback'"
      class="smgp-activity-content smgp-activity-content--feedback"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <p v-if="inline.iscomplete" class="smgp-feedback__completed">
        {{ $t('course_page.feedback_completed') }}
      </p>

      <template v-else-if="inline.pages && inline.pages.length > 0">
        <div class="smgp-feedback__progress">
          {{ $t('course_page.feedback_page_of', { current: feedbackPage + 1, total: inline.pages.length }) }}
        </div>

        <div class="smgp-feedback__items">
          <div
            v-for="item in inline.pages[feedbackPage]"
            :key="item.id"
            class="smgp-feedback__item"
          >
            <label class="smgp-feedback__label">
              {{ item.name }}
              <span v-if="item.required" class="smgp-feedback__required">*</span>
            </label>

            <!-- Multichoice (radio) -->
            <div v-if="item.typ === 'multichoice'" class="smgp-feedback__options">
              <label
                v-for="(opt, idx) in parseFeedbackOptions(item.options)"
                :key="idx"
                class="smgp-feedback__option"
              >
                <input
                  v-model="feedbackValues[item.id]"
                  type="radio"
                  :name="'fb_' + item.id"
                  :value="String(idx + 1)"
                >
                <span>{{ opt }}</span>
              </label>
            </div>

            <!-- Multichoicerated (radio with rates) -->
            <div v-else-if="item.typ === 'multichoicerated'" class="smgp-feedback__options">
              <label
                v-for="(opt, idx) in parseFeedbackOptions(item.options)"
                :key="idx"
                class="smgp-feedback__option"
              >
                <input
                  v-model="feedbackValues[item.id]"
                  type="radio"
                  :name="'fb_' + item.id"
                  :value="String(idx + 1)"
                >
                <span>{{ opt }}</span>
              </label>
            </div>

            <!-- Textarea -->
            <textarea
              v-else-if="item.typ === 'textarea'"
              v-model="feedbackValues[item.id]"
              class="smgp-feedback__textarea"
              rows="4"
            />

            <!-- Textfield (short text) -->
            <input
              v-else-if="item.typ === 'textfield'"
              v-model="feedbackValues[item.id]"
              type="text"
              class="smgp-feedback__input"
            >

            <!-- Numeric -->
            <input
              v-else-if="item.typ === 'numeric'"
              v-model="feedbackValues[item.id]"
              type="number"
              class="smgp-feedback__input"
            >

            <!-- Info / Label — display only -->
            <div v-else-if="item.typ === 'info' || item.typ === 'label'" class="smgp-feedback__info">
              {{ item.label }}
            </div>
          </div>
        </div>

        <div class="smgp-feedback__nav">
          <button
            v-if="feedbackPage > 0"
            class="btn btn-outline-secondary btn-sm"
            @click="feedbackPage--"
          >
            {{ $t('course_page.feedback_prev_page') }}
          </button>
          <span v-else />
          <button
            v-if="feedbackPage < inline.pages.length - 1"
            class="btn btn-success btn-sm"
            :disabled="feedbackSubmitting"
            @click="submitFeedbackPage(false)"
          >
            {{ $t('course_page.feedback_next_page') }}
          </button>
          <button
            v-else
            class="btn btn-success btn-sm"
            :disabled="feedbackSubmitting"
            @click="submitFeedbackPage(true)"
          >
            {{ $t('course_page.feedback_submit') }}
          </button>
        </div>
      </template>
    </div>

    <!-- mod_wiki: page viewer with navigation -->
    <div
      v-else-if="inline.kind === 'wiki'"
      class="smgp-activity-content smgp-activity-content--wiki"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <!-- Page list tabs -->
      <div v-if="inline.pages && inline.pages.length > 0" class="smgp-wiki__page-list">
        <span class="smgp-wiki__pages-label">{{ $t('course_page.wiki_pages') }}:</span>
        <button
          v-for="p in inline.pages"
          :key="p.id"
          class="smgp-wiki__page-btn"
          :class="{ 'smgp-wiki__page-btn--active': inline.page?.id === p.id }"
          @click="$emit('bookNavigate', p.id)"
        >
          {{ p.title }}
        </button>
      </div>

      <template v-if="inline.page">
        <template v-if="!wikiEditing">
          <h3 class="smgp-wiki__title">{{ inline.page.title }}</h3>
          <div class="smgp-wiki__content" v-html="inline.page.content" />
          <button class="btn btn-outline-secondary btn-sm mt-3" @click="startWikiEdit">
            <i class="bi bi-pencil me-1" />{{ $t('course_page.wiki_edit') }}
          </button>
        </template>
        <template v-else>
          <textarea v-model="wikiEditContent" class="smgp-wiki__editor" rows="12" />
          <div class="smgp-wiki__edit-actions">
            <button class="btn btn-success btn-sm" :disabled="wikiSaving" @click="saveWikiPage">
              {{ $t('course_page.wiki_save') }}
            </button>
            <button class="btn btn-outline-secondary btn-sm" @click="wikiEditing = false">
              {{ $t('course_page.wiki_cancel') }}
            </button>
          </div>
        </template>
      </template>

      <p v-else class="smgp-wiki__empty">
        {{ $t('course_page.wiki_no_pages') }}
      </p>
    </div>

    <!-- mod_data: database entries table -->
    <div
      v-else-if="inline.kind === 'data'"
      class="smgp-activity-content smgp-activity-content--data"
    >
      <div
        v-if="inline.intro"
        class="smgp-activity-content__intro"
        v-html="inline.intro"
      />

      <div class="smgp-data__header">
        <span class="smgp-data__count">
          {{ $t('course_page.data_entries_count', { count: inline.totalentries ?? 0 }) }}
        </span>
        <button
          v-if="inline.canaddentry && !dataAdding"
          class="btn btn-success btn-sm"
          @click="dataAdding = true"
        >
          <i class="bi bi-plus me-1" />{{ $t('course_page.data_add_entry') }}
        </button>
      </div>

      <!-- Add entry form -->
      <div v-if="dataAdding" class="smgp-data__add-form">
        <div
          v-for="field in inline.fields"
          :key="field.id"
          class="smgp-data__field"
        >
          <label>{{ field.name }}</label>
          <input
            v-if="field.type === 'text' || field.type === 'url' || field.type === 'number'"
            v-model="dataNewEntry[field.id]"
            :type="field.type === 'number' ? 'number' : 'text'"
            class="smgp-data__input"
          >
          <textarea
            v-else-if="field.type === 'textarea'"
            v-model="dataNewEntry[field.id]"
            class="smgp-data__textarea"
            rows="3"
          />
          <select
            v-else-if="field.type === 'menu'"
            v-model="dataNewEntry[field.id]"
            class="smgp-data__select"
          >
            <option value="">--</option>
            <option
              v-for="(opt, idx) in (field.param1 || '').split('\n')"
              :key="idx"
              :value="opt.trim()"
            >
              {{ opt.trim() }}
            </option>
          </select>
          <label v-else-if="field.type === 'checkbox'" class="smgp-data__checkbox">
            <input v-model="dataNewEntry[field.id]" type="checkbox" true-value="1" false-value="0">
            {{ field.name }}
          </label>
          <input
            v-else
            v-model="dataNewEntry[field.id]"
            type="text"
            class="smgp-data__input"
          >
        </div>
        <div class="smgp-data__form-actions">
          <button class="btn btn-success btn-sm" :disabled="dataSaving" @click="submitDataEntry">
            {{ $t('course_page.data_save') }}
          </button>
          <button class="btn btn-outline-secondary btn-sm" @click="dataAdding = false">
            {{ $t('course_page.wiki_cancel') }}
          </button>
        </div>
      </div>

      <!-- Entries table -->
      <div v-if="inline.entries && inline.entries.length > 0" class="smgp-data__table-wrap">
        <table class="smgp-data__table">
          <thead>
            <tr>
              <th v-for="f in inline.fields" :key="f.id">{{ f.name }}</th>
              <th>Author</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in inline.entries" :key="entry.id">
              <td v-for="f in inline.fields" :key="f.id">
                {{ entry.fields[f.id]?.content || '' }}
              </td>
              <td>{{ entry.userfullname }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="smgp-data__empty">{{ $t('course_page.data_no_entries') }}</p>
    </div>

    <!-- mod_quiz: 3-state quiz player -->
    <div
      v-else-if="inline.kind === 'quiz'"
      class="smgp-activity-content smgp-activity-content--quiz"
    >
      <div v-if="inline.intro" class="smgp-activity-content__intro" v-html="inline.intro" />

      <!-- Not started -->
      <div v-if="inline.state === 'notstarted' || (inline.state === 'finished' && !quizReviewData)" class="smgp-quiz__start-card">
        <h3>{{ inline.name }}</h3>
        <p v-if="inline.timelimit" class="smgp-quiz__meta">
          {{ $t('course_page.quiz_time_limit', { time: formatDuration(inline.timelimit!) }) }}
        </p>
        <p class="smgp-quiz__meta">
          {{ inline.attemptsallowed
            ? $t('course_page.quiz_attempts_info', { used: inline.attemptsused, allowed: inline.attemptsallowed })
            : $t('course_page.quiz_attempts_unlimited', { used: inline.attemptsused }) }}
        </p>
        <p v-if="inline.state === 'finished' && inline.grade !== null && inline.grade !== undefined" class="smgp-quiz__grade">
          {{ $t('course_page.quiz_grade', { grade: inline.grade, max: inline.grademax || 100 }) }}
        </p>
        <div class="smgp-quiz__actions">
          <button
            v-if="inline.canstartnew"
            class="btn btn-success"
            :disabled="quizStarting"
            @click="startQuiz"
          >
            {{ inline.attemptsused ? $t('course_page.quiz_start') : $t('course_page.quiz_start') }}
          </button>
          <button
            v-if="inline.state === 'finished' && inline.reviewavailable && inline.lastattemptid"
            class="btn btn-outline-secondary"
            :disabled="quizReviewLoading"
            @click="loadQuizReview(inline.lastattemptid!)"
          >
            {{ $t('course_page.quiz_review') }}
          </button>
          <p v-if="!inline.canstartnew && inline.state !== 'finished'" class="smgp-quiz__no-attempts">
            {{ $t('course_page.quiz_no_attempts') }}
          </p>
        </div>
      </div>

      <!-- In progress -->
      <div v-else-if="inline.state === 'inprogress' && inline.questions" class="smgp-quiz__attempt">
        <div
          v-for="q in inline.questions"
          :key="q.slot"
          class="smgp-quiz__question"
        >
          <div class="smgp-quiz__question-text" v-html="q.text" />

          <!-- Multichoice -->
          <div v-if="q.type === 'multichoice'" class="smgp-quiz__choices">
            <label v-for="c in q.choices" :key="c.value" class="smgp-quiz__choice">
              <input
                v-if="q.single"
                v-model="quizAnswers[q.slot]"
                type="radio"
                :name="'q_' + q.slot"
                :value="String(c.value)"
              >
              <input v-else type="checkbox" :value="String(c.value)">
              <span v-html="c.label" />
            </label>
          </div>

          <!-- True/False -->
          <div v-else-if="q.type === 'truefalse'" class="smgp-quiz__choices">
            <label v-for="c in q.choices" :key="c.value" class="smgp-quiz__choice">
              <input v-model="quizAnswers[q.slot]" type="radio" :name="'q_' + q.slot" :value="String(c.value)">
              <span>{{ c.label }}</span>
            </label>
          </div>

          <!-- Short answer / Numerical -->
          <input
            v-else-if="q.type === 'shortanswer' || q.type === 'numerical'"
            v-model="quizAnswers[q.slot]"
            :type="q.inputtype || 'text'"
            class="smgp-quiz__text-input"
          >

          <!-- Essay -->
          <textarea
            v-else-if="q.type === 'essay'"
            v-model="quizAnswers[q.slot]"
            class="smgp-quiz__essay"
            rows="6"
          />

          <!-- Description (info only) -->
          <div v-else-if="q.type === 'description'" />
        </div>

        <div class="smgp-quiz__nav">
          <button class="btn btn-success btn-sm" :disabled="quizSubmitting" @click="submitQuizPage">
            {{ $t('course_page.quiz_submit_page') }}
          </button>
          <button class="btn btn-danger btn-sm" :disabled="quizSubmitting" @click="finishQuiz">
            {{ $t('course_page.quiz_finish') }}
          </button>
        </div>
      </div>

      <!-- Review -->
      <div v-else-if="quizReviewData" class="smgp-quiz__review">
        <div class="smgp-quiz__review-header">
          <h3>{{ $t('course_page.quiz_review') }}</h3>
          <p v-if="quizReviewData.grade !== null" class="smgp-quiz__grade">
            {{ $t('course_page.quiz_grade', { grade: quizReviewData.grade, max: quizReviewData.grademax }) }}
          </p>
        </div>
        <div
          v-for="q in quizReviewData.questions"
          :key="q.slot"
          class="smgp-quiz__review-question"
          :class="{ 'smgp-quiz__review-question--correct': q.iscorrect, 'smgp-quiz__review-question--incorrect': !q.iscorrect && !q.ispartial, 'smgp-quiz__review-question--partial': q.ispartial }"
        >
          <div class="smgp-quiz__question-text" v-html="q.text" />
          <div class="smgp-quiz__review-answer">
            <span class="smgp-quiz__review-state">{{ q.statelabel }}</span>
            <span v-if="q.mark !== null">{{ q.mark }} / {{ q.maxmark }}</span>
          </div>
          <div v-if="q.responsesummary" class="smgp-quiz__review-response">
            <strong>Your answer:</strong> {{ q.responsesummary }}
          </div>
          <div v-if="q.rightanswer" class="smgp-quiz__review-correct">
            <strong>Correct answer:</strong> {{ q.rightanswer }}
          </div>
          <div v-if="q.generalfeedback" class="smgp-quiz__review-feedback" v-html="q.generalfeedback" />
        </div>
        <button class="btn btn-outline-secondary btn-sm mt-3" @click="quizReviewData = null">
          {{ $t('course_page.prev') }}
        </button>
      </div>
    </div>

    <!-- mod_assign: assignment submission -->
    <div
      v-else-if="inline.kind === 'assign'"
      class="smgp-activity-content smgp-activity-content--assign"
    >
      <div v-if="inline.intro" class="smgp-activity-content__intro" v-html="inline.intro" />

      <!-- Status info -->
      <div class="smgp-assign__status">
        <span v-if="inline.duedate" class="smgp-assign__due">
          {{ $t('course_page.assign_due', { date: new Date(inline.duedate! * 1000).toLocaleDateString() }) }}
        </span>
        <span v-if="inline.submissionstatus === 'submitted'" class="smgp-assign__badge smgp-assign__badge--submitted">
          {{ $t('course_page.assign_submitted') }}
        </span>
        <span v-if="inline.isgraded" class="smgp-assign__badge smgp-assign__badge--graded">
          {{ $t('course_page.assign_graded', { grade: inline.gradevalue, max: inline.grademax }) }}
        </span>
      </div>

      <!-- Grade + feedback display -->
      <div v-if="inline.feedbackcomments" class="smgp-assign__feedback">
        <h4>{{ $t('course_page.assign_feedback') }}</h4>
        <div v-html="inline.feedbackcomments" />
      </div>

      <!-- Submission form -->
      <div v-if="inline.submissionstatus !== 'submitted' || inline.attemptreopenmethod !== 'none'" class="smgp-assign__form">
        <div v-if="inline.submissiontypes?.includes('onlinetext')" class="smgp-assign__section">
          <label>{{ $t('course_page.assign_text_editor') }}</label>
          <textarea v-model="assignText" class="smgp-assign__textarea" rows="8" />
        </div>

        <div v-if="inline.submissiontypes?.includes('file')" class="smgp-assign__section">
          <label>{{ $t('course_page.assign_file_upload') }}</label>
          <div v-if="inline.filesubmissions?.length" class="smgp-assign__files">
            <div v-for="f in inline.filesubmissions" :key="f.url" class="smgp-assign__file-row">
              <i class="bi bi-file-earmark" />
              <a :href="f.url" target="_blank">{{ f.name }}</a>
              <span class="smgp-assign__file-size">{{ f.size }}</span>
            </div>
          </div>
        </div>

        <div class="smgp-assign__actions">
          <button
            class="btn btn-success btn-sm"
            :disabled="assignSubmitting"
            @click="submitAssignment(true)"
          >
            {{ $t('course_page.assign_submit') }}
          </button>
          <button
            class="btn btn-outline-secondary btn-sm"
            :disabled="assignSubmitting"
            @click="submitAssignment(false)"
          >
            {{ $t('course_page.assign_save_draft') }}
          </button>
        </div>
      </div>
    </div>

    <!-- mod_lesson: branching lesson player -->
    <div
      v-else-if="inline.kind === 'lesson'"
      class="smgp-activity-content smgp-activity-content--lesson"
    >
      <div v-if="inline.intro && !inline.page" class="smgp-activity-content__intro" v-html="inline.intro" />

      <!-- Lesson page -->
      <template v-if="inline.page">
        <h3 class="smgp-lesson__title">{{ inline.page.title }}</h3>
        <div class="smgp-lesson__content" v-html="inline.page.content" />

        <!-- Feedback overlay -->
        <div v-if="lessonFeedback" class="smgp-lesson__feedback" :class="lessonFeedbackCorrect ? 'smgp-lesson__feedback--correct' : 'smgp-lesson__feedback--incorrect'">
          <p>{{ lessonFeedbackCorrect ? $t('course_page.lesson_correct') : $t('course_page.lesson_incorrect') }}</p>
          <div v-if="lessonFeedback !== 'true' && lessonFeedback !== 'false'" v-html="lessonFeedback" />
          <button class="btn btn-sm btn-outline-secondary mt-2" @click="lessonFeedback = ''">
            {{ $t('course_page.lesson_continue') }}
          </button>
        </div>

        <!-- Content page (branching) — show answer buttons as navigation -->
        <div v-if="inline.page.typelabel === 'content' && inline.answers" class="smgp-lesson__branches">
          <button
            v-for="ans in inline.answers"
            :key="ans.id"
            class="btn btn-outline-secondary smgp-lesson__branch-btn"
            :disabled="lessonProcessing"
            @click="processLessonPage(ans.id)"
          >
            <span v-html="ans.text" />
          </button>
        </div>

        <!-- Question page — show answer options + submit -->
        <div v-else-if="inline.answers && inline.answers.length > 0" class="smgp-lesson__question">
          <div class="smgp-lesson__answer-options">
            <label v-for="ans in inline.answers" :key="ans.id" class="smgp-lesson__answer-option">
              <input v-model="lessonSelectedAnswer" type="radio" name="lesson_answer" :value="ans.id">
              <span v-html="ans.text" />
            </label>
          </div>
          <button
            class="btn btn-success btn-sm mt-3"
            :disabled="lessonProcessing || !lessonSelectedAnswer"
            @click="processLessonPage(lessonSelectedAnswer!)"
          >
            {{ $t('course_page.lesson_check_answer') }}
          </button>
        </div>
      </template>

      <!-- Lesson complete -->
      <div v-if="lessonFinished" class="smgp-lesson__complete">
        <i class="bi bi-check-circle" />
        <p>{{ $t('course_page.lesson_complete') }}</p>
      </div>
    </div>

    <!-- mod_workshop: multi-phase workshop -->
    <div
      v-else-if="inline.kind === 'workshop'"
      class="smgp-activity-content smgp-activity-content--workshop"
    >
      <div v-if="inline.intro" class="smgp-activity-content__intro" v-html="inline.intro" />

      <!-- Phase indicator -->
      <div class="smgp-workshop__phase">
        <span
          v-for="p in ['setup', 'submission', 'assessment', 'grading', 'closed']"
          :key="p"
          class="smgp-workshop__phase-step"
          :class="{ 'smgp-workshop__phase-step--active': inline.phase === p }"
        >
          {{ $t('course_page.workshop_phase_' + p) }}
        </span>
      </div>

      <!-- Submission phase: show form -->
      <template v-if="inline.phase === 'submission' && inline.cansubmit">
        <div v-if="inline.submission" class="smgp-workshop__existing">
          <h4>{{ $t('course_page.workshop_your_submission') }}</h4>
          <p><strong>{{ inline.submission.title }}</strong></p>
          <div v-html="inline.submission.content" />
        </div>
        <div class="smgp-workshop__submit-form">
          <div class="smgp-workshop__field">
            <label>{{ $t('course_page.workshop_title_label') }}</label>
            <input v-model="workshopTitle" type="text" class="smgp-workshop__input">
          </div>
          <div class="smgp-workshop__field">
            <label>{{ $t('course_page.workshop_content_label') }}</label>
            <textarea v-model="workshopContent" class="smgp-workshop__textarea" rows="6" />
          </div>
          <button class="btn btn-success btn-sm" :disabled="workshopSubmitting" @click="submitWorkshop">
            {{ inline.submission ? $t('course_page.workshop_edit_submission') : $t('course_page.workshop_submit') }}
          </button>
        </div>
      </template>

      <!-- Assessment phase: show assigned assessments -->
      <template v-else-if="inline.phase === 'assessment'">
        <div v-if="inline.assessments?.length" class="smgp-workshop__assessments">
          <h4>{{ $t('course_page.workshop_assessments') }}</h4>
          <div v-for="a in inline.assessments" :key="a.id" class="smgp-workshop__assessment-card">
            <strong>{{ a.submissiontitle }}</strong>
            <span v-if="a.grade !== null" class="smgp-workshop__assessment-grade">{{ a.grade }}</span>
          </div>
        </div>
      </template>

      <!-- Other phases: show submission status -->
      <template v-else>
        <div v-if="inline.submission" class="smgp-workshop__existing">
          <h4>{{ $t('course_page.workshop_your_submission') }}</h4>
          <p><strong>{{ inline.submission.title }}</strong></p>
          <span v-if="inline.submission.grade !== null" class="smgp-workshop__grade">
            {{ $t('course_page.quiz_grade', { grade: inline.submission.grade, max: 100 }) }}
          </span>
        </div>
        <p v-else class="smgp-workshop__no-submission">{{ $t('course_page.workshop_no_submission') }}</p>
      </template>
    </div>

    <!-- mod_scorm: Vue-native SCORM player -->
    <CoursePlayerScorm
      v-else-if="inline.kind === 'scorm'"
      :cmid="getCurrentCmid()"
      @progress="onScormProgress"
      @complete="emit('activityUpdated')"
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

const props = defineProps<{
  loading: boolean
  render: ActivityRender
  inline: InlineData | null
  iframeUrl: string | null
  redirectUrl?: string | null
}>()

const emit = defineEmits<{
  (e: 'bookNavigate', chapterNum: number): void
  (e: 'activityUpdated'): void
  (e: 'scormProgress', completed: number, total: number): void
}>()

const { call } = useMoodleAjax()

// ── Book (client-side chapter navigation) ─────────────────────────────
const bookChapterIndex = ref(0)

// Reset chapter index when the activity changes.
watch(() => props.inline, (data) => {
  if (data?.kind === 'book' && data.chapter) {
    bookChapterIndex.value = (data.chapter.current ?? 1) - 1
  }
}, { immediate: true })

const bookTotalChapters = computed(() =>
  props.inline?.allchapters?.length ?? props.inline?.chapter?.total ?? 0,
)

const bookCurrentChapter = computed(() => bookChapterIndex.value + 1)

const bookDisplayTitle = computed(() => {
  const ch = props.inline?.allchapters?.[bookChapterIndex.value]
  return ch?.title ?? props.inline?.chapter?.title ?? ''
})

const bookDisplayContent = computed(() => {
  const ch = props.inline?.allchapters?.[bookChapterIndex.value]
  return ch?.content ?? props.inline?.content ?? ''
})

function navigateBookChapter(chapterNum: number) {
  if (chapterNum < 1 || chapterNum > bookTotalChapters.value) return
  bookChapterIndex.value = chapterNum - 1

  // Fire the backend event for progress tracking (non-blocking).
  // This records the chapter_viewed event and checks completion.
  emit('bookNavigate', chapterNum)
}

// ── Glossary ──────────────────────────────────────────────────────────
const glossarySearch = ref('')

const filteredGlossaryEntries = computed(() => {
  const entries = props.inline?.entries ?? []
  if (!glossarySearch.value) return entries
  const q = glossarySearch.value.toLowerCase()
  return entries.filter(e =>
    e.concept.toLowerCase().includes(q) || e.definition.toLowerCase().includes(q),
  )
})

// ── Choice ────────────────────────────────────────────────────────────
const choiceSelected = ref<number[]>([])
const choiceSingleSelected = ref<number | null>(null)
const choiceSubmitting = ref(false)

// Initialize selection from existing answer.
watch(() => props.inline, (data) => {
  if (data?.kind === 'choice' && data.options) {
    const selected = data.options.filter(o => o.selected).map(o => o.id)
    choiceSelected.value = selected
    choiceSingleSelected.value = selected[0] ?? null
  }
  // Reset survey answers when activity changes.
  if (data?.kind === 'survey') {
    surveyAnswers.value = {}
  }
}, { immediate: true })

function choiceBarWidth(count: number): number {
  const results = props.inline?.results ?? []
  const max = Math.max(...results.map(r => r.count), 1)
  return Math.round((count / max) * 100)
}

async function submitChoice() {
  if (!props.inline?.choiceid) return
  const responses = props.inline.allowmultiple
    ? choiceSelected.value
    : (choiceSingleSelected.value ? [choiceSingleSelected.value] : [])
  if (responses.length === 0) return

  choiceSubmitting.value = true
  // Find cmid from parent — we need to pass it. Use the route param approach.
  const result = await call('local_sm_graphics_plugin_submit_choice_response', {
    cmid: getCurrentCmid(),
    responses,
  })
  choiceSubmitting.value = false
  if (!result.error) {
    emit('activityUpdated')
  }
}

// ── Survey ────────────────────────────────────────────────────────────
const surveyAnswers = ref<Record<number, string>>({})
const surveySubmitting = ref(false)

async function submitSurvey() {
  if (!props.inline?.surveyid || !props.inline.questions) return
  const answers = props.inline.questions
    .filter(q => surveyAnswers.value[q.id])
    .map(q => ({ questionid: q.id, answer: surveyAnswers.value[q.id] }))

  surveySubmitting.value = true
  const result = await call('local_sm_graphics_plugin_submit_survey_answers', {
    cmid: getCurrentCmid(),
    answers,
  })
  surveySubmitting.value = false
  if (!result.error) {
    emit('activityUpdated')
  }
}

// ── Feedback ──────────────────────────────────────────────────────────
const feedbackPage = ref(0)
const feedbackValues = ref<Record<number, string>>({})
const feedbackSubmitting = ref(false)

watch(() => props.inline, (data) => {
  if (data?.kind === 'feedback') {
    feedbackPage.value = 0
    feedbackValues.value = {}
    if (data.savedvalues) {
      for (const [k, v] of Object.entries(data.savedvalues)) {
        feedbackValues.value[Number(k)] = String(v)
      }
    }
  }
})

function parseFeedbackOptions(presentation: string): string[] {
  if (!presentation) return []
  const pipe = presentation.replace(/^[a-z]>>>>>/i, '')
  return pipe.split('|').map(s => s.trim()).filter(Boolean)
}

async function submitFeedbackPage(finish: boolean) {
  const pageItems: any[] = props.inline?.pages?.[feedbackPage.value] ?? []
  const responses = pageItems
    .filter((item: any) => item.typ !== 'info' && item.typ !== 'label' && item.typ !== 'pagebreak')
    .map((item: any) => ({ itemid: item.id, value: feedbackValues.value[item.id] ?? '' }))

  feedbackSubmitting.value = true
  const result = await call('local_sm_graphics_plugin_submit_feedback_page', {
    cmid: getCurrentCmid(),
    pagenum: feedbackPage.value,
    responses,
    finish,
  })
  feedbackSubmitting.value = false
  if (!result.error) {
    if (finish) {
      emit('activityUpdated')
    } else {
      feedbackPage.value++
    }
  }
}

// ── Wiki ──────────────────────────────────────────────────────────────
const wikiEditing = ref(false)
const wikiEditContent = ref('')
const wikiSaving = ref(false)

watch(() => props.inline, () => {
  wikiEditing.value = false
})

function startWikiEdit() {
  wikiEditContent.value = props.inline?.page?.content ?? ''
  wikiEditing.value = true
}

async function saveWikiPage() {
  wikiSaving.value = true
  await call('local_sm_graphics_plugin_save_wiki_page', {
    cmid: getCurrentCmid(),
    pageid: props.inline?.page?.id ?? 0,
    title: props.inline?.page?.title ?? '',
    content: wikiEditContent.value,
  })
  wikiSaving.value = false
  wikiEditing.value = false
  emit('activityUpdated')
}

// ── Data (database) ──────────────────────────────────────────────────
const dataAdding = ref(false)
const dataNewEntry = ref<Record<number, string>>({})
const dataSaving = ref(false)

watch(() => props.inline, (data) => {
  if (data?.kind === 'data') {
    dataAdding.value = false
    dataNewEntry.value = {}
  }
})

async function submitDataEntry() {
  const fields = (props.inline?.fields ?? []).map(f => ({
    fieldid: f.id,
    value: dataNewEntry.value[f.id] ?? '',
  }))
  dataSaving.value = true
  const result = await call('local_sm_graphics_plugin_add_data_entry', {
    cmid: getCurrentCmid(),
    fields,
  })
  dataSaving.value = false
  if (!result.error) {
    dataAdding.value = false
    dataNewEntry.value = {}
    emit('activityUpdated')
  }
}

// ── Quiz ──────────────────────────────────────────────────────────────
const quizAnswers = ref<Record<number, string>>({})
const quizStarting = ref(false)
const quizSubmitting = ref(false)
const quizReviewData = ref<any>(null)
const quizReviewLoading = ref(false)

watch(() => props.inline, (data) => {
  if (data?.kind === 'quiz') {
    quizAnswers.value = {}
    quizReviewData.value = null
    // Restore saved responses from in-progress attempt.
    if (data.state === 'inprogress' && data.questions) {
      for (const q of data.questions) {
        if (q.savedresponse?.answer !== undefined) {
          quizAnswers.value[q.slot] = q.savedresponse.answer
        }
      }
    }
  }
})

async function startQuiz() {
  quizStarting.value = true
  const result = await call('local_sm_graphics_plugin_start_quiz_attempt', {
    cmid: getCurrentCmid(),
  })
  quizStarting.value = false
  if (!result.error) {
    emit('activityUpdated')
  }
}

async function submitQuizPage() {
  if (!props.inline?.attemptid || !props.inline.questions) return
  const answers = props.inline.questions
    .filter(q => q.type !== 'description')
    .map(q => ({
      slot: q.slot,
      sequencecheck: q.sequencecheck,
      response: [{ name: 'answer', value: quizAnswers.value[q.slot] ?? '' }],
    }))

  quizSubmitting.value = true
  const nextpage = (props.inline.currentpage ?? 0) + 1
  await call('local_sm_graphics_plugin_submit_quiz_answers', {
    attemptid: props.inline.attemptid,
    answers,
    nextpage: nextpage < (props.inline.totalpages ?? 1) ? nextpage : -1,
  })
  quizSubmitting.value = false
  emit('activityUpdated')
}

async function finishQuiz() {
  if (!props.inline?.attemptid) return
  if (!confirm(props.inline.name + '?\n\n' + '')) {
    // Simple confirm — will be replaced with modal later.
  }
  quizSubmitting.value = true
  const result = await call('local_sm_graphics_plugin_finish_quiz_attempt', {
    attemptid: props.inline.attemptid,
  })
  quizSubmitting.value = false
  if (!result.error) {
    emit('activityUpdated')
  }
}

async function loadQuizReview(attemptid: number) {
  quizReviewLoading.value = true
  const result = await call('local_sm_graphics_plugin_get_quiz_review', { attemptid })
  quizReviewLoading.value = false
  if (!result.error) {
    quizReviewData.value = result.data
  }
}

function formatDuration(seconds: number): string {
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  if (h > 0) return `${h}h ${m}m`
  return `${m} min`
}

// ── Assignment ────────────────────────────────────────────────────────
const assignText = ref('')
const assignSubmitting = ref(false)

watch(() => props.inline, (data) => {
  if (data?.kind === 'assign') {
    // Strip HTML for textarea if existing online text.
    assignText.value = data.onlinetext
      ? data.onlinetext.replace(/<[^>]*>/g, '')
      : ''
  }
})

async function submitAssignment(forGrading: boolean) {
  assignSubmitting.value = true
  await call('local_sm_graphics_plugin_save_assignment_submission', {
    cmid: getCurrentCmid(),
    onlinetext: assignText.value,
    draftitemid: 0,
    submitforgrading: forGrading,
  })
  assignSubmitting.value = false
  emit('activityUpdated')
}

// ── Lesson ────────────────────────────────────────────────────────────
const lessonSelectedAnswer = ref<number | null>(null)
const lessonProcessing = ref(false)
const lessonFeedback = ref('')
const lessonFeedbackCorrect = ref(false)
const lessonFinished = ref(false)

watch(() => props.inline, (data) => {
  if (data?.kind === 'lesson') {
    lessonSelectedAnswer.value = null
    lessonFeedback.value = ''
    lessonFinished.value = false
  }
})

async function processLessonPage(answerid: number) {
  if (!props.inline?.lessonid || !props.inline.page) return
  lessonProcessing.value = true
  const result = await call('local_sm_graphics_plugin_process_lesson_page', {
    cmid: getCurrentCmid(),
    pageid: props.inline.page.id,
    answerid,
  })
  lessonProcessing.value = false
  if (!result.error) {
    const d = result.data as any
    if (d.lessonfinished) {
      lessonFinished.value = true
      emit('activityUpdated')
      return
    }
    if (d.feedback) {
      lessonFeedback.value = d.feedback
      lessonFeedbackCorrect.value = d.iscorrect
    }
    if (d.nextpageid > 0 && !d.feedback) {
      // Navigate to next page by re-fetching with the page id.
      const content = await call('local_sm_graphics_plugin_get_activity_content', {
        cmid: getCurrentCmid(),
        itemnum: d.nextpageid,
      })
      if (!content.error) {
        emit('activityUpdated')
      }
    }
    lessonSelectedAnswer.value = null
  }
}

// ── Workshop ──────────────────────────────────────────────────────────
const workshopTitle = ref('')
const workshopContent = ref('')
const workshopSubmitting = ref(false)

watch(() => props.inline, (data) => {
  if (data?.kind === 'workshop') {
    workshopTitle.value = data.submission?.title ?? ''
    workshopContent.value = data.submission?.content?.replace(/<[^>]*>/g, '') ?? ''
  }
})

async function submitWorkshop() {
  workshopSubmitting.value = true
  await call('local_sm_graphics_plugin_submit_workshop_submission', {
    cmid: getCurrentCmid(),
    title: workshopTitle.value,
    content: workshopContent.value,
    draftitemid: 0,
  })
  workshopSubmitting.value = false
  emit('activityUpdated')
}

// ── SCORM progress bridge ─────────────────────────────────────────────
function onScormProgress(completed: number, total: number) {
  emit('scormProgress', completed, total)
}

// ── Helpers ───────────────────────────────────────────────────────────
function getCurrentCmid(): number {
  return inject<Ref<number>>('selectedCmid')?.value ?? 0
}
</script>
