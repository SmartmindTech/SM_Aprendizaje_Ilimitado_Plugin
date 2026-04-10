<template>
  <div class="smgp-restore">
    <!-- ════════════════════════════════════════════════════════════
         Landing page (step 0) — only shown on direct visits, when
         the user has NOT come from the SharePoint courseloader. As
         soon as a backup is prepared (manual upload OR sharepoint
         handoff), step advances to 1 (Confirm).
         ════════════════════════════════════════════════════════════ -->
    <section v-if="step === 0" class="smgp-restore__landing">
      <header class="smgp-restore__landing-header">
        <i class="bi bi-arrow-counterclockwise smgp-restore__landing-icon" />
        <div>
          <h1 class="smgp-restore__landing-title">{{ $t('restore.landing_title') }}</h1>
          <p class="smgp-restore__landing-desc">{{ $t('restore.landing_desc') }}</p>
        </div>
      </header>

      <div class="smgp-restore__landing-card">
        <div class="smgp-restore__landing-card-header">
          <i class="bi bi-cloud-upload" />
          <h2>{{ $t('restore.landing_import_title') }}</h2>
        </div>

        <div class="smgp-restore__landing-card-body">
          <button
            type="button"
            class="btn btn-success smgp-restore__landing-pick"
            @click="triggerLandingPicker"
          >
            {{ $t('restore.landing_pick') }}
          </button>

          <label
            class="smgp-restore__dropzone"
            :class="{ 'is-dragover': dropzoneDragover, 'has-file': !!landingFile }"
            @dragover.prevent="dropzoneDragover = true"
            @dragleave.prevent="dropzoneDragover = false"
            @drop.prevent="onLandingDrop"
          >
            <input
              ref="landingFileInput"
              type="file"
              accept=".mbz"
              class="smgp-restore__dropzone-input"
              @change="onLandingFileChange"
            >
            <i class="bi bi-arrow-down-circle smgp-restore__dropzone-icon" />
            <span class="smgp-restore__dropzone-text">
              {{ landingFile ? landingFile.name : $t('restore.landing_dropzone_hint') }}
            </span>
          </label>

          <div class="smgp-restore__landing-actions">
            <button
              type="button"
              class="btn btn-success smgp-restore__landing-restore"
              :disabled="!landingFile || uploading"
              @click="uploadLandingFile"
            >
              <span v-if="uploading" class="spinner-border spinner-border-sm me-1" />
              {{ uploading ? $t('restore.uploading') : $t('restore.landing_restore') }}
            </button>
          </div>

          <div v-if="!landingFile && landingError" class="smgp-restore__landing-required">
            <i class="bi bi-exclamation-circle" />
            <span>{{ landingError }}</span>
          </div>
        </div>
      </div>

      <!-- ─── Course backup zone ──────────────────────────── -->
      <div class="smgp-restore__landing-zone">
        <div class="smgp-restore__landing-zone-title">
          <i class="bi bi-folder" />
          <h3>{{ $t('restore.zone_course_title') }}</h3>
        </div>
        <p class="smgp-restore__landing-zone-desc">{{ $t('restore.zone_course_desc') }}</p>
        <div class="smgp-restore__zone-table">
          <table class="table">
            <thead>
              <tr>
                <th>{{ $t('restore.col_filename') }}</th>
                <th>{{ $t('restore.col_time') }}</th>
                <th>{{ $t('restore.col_size') }}</th>
                <th>{{ $t('restore.col_download') }}</th>
                <th>{{ $t('restore.col_restore') }}</th>
                <th>{{ $t('restore.col_status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(b, i) in zonesData?.course_backups || []" :key="`cb-${i}-${b.filename}`">
                <td>
                  <strong>{{ b.filename }}</strong>
                  <div v-if="b.coursename" class="text-muted small">{{ b.coursename }}</div>
                </td>
                <td>{{ b.time ? new Date(b.time * 1000).toLocaleString() : '—' }}</td>
                <td>{{ formatBytes(b.size) }}</td>
                <td><a :href="b.downloadurl" target="_blank" class="btn btn-sm btn-outline-success">{{ $t('restore.col_download') }}</a></td>
                <td><button class="btn btn-sm btn-success" @click="restoreFromArea(b)">{{ $t('restore.col_restore') }}</button></td>
                <td class="text-muted">{{ statusLabel(b.status) }}</td>
              </tr>
              <tr v-if="!(zonesData?.course_backups || []).length">
                <td colspan="6" class="text-center text-muted small">{{ $t('restore.zone_empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ─── User private backup zone ────────────────────── -->
      <div class="smgp-restore__landing-zone">
        <div class="smgp-restore__landing-zone-title">
          <i class="bi bi-person" />
          <h3>{{ $t('restore.zone_user_title') }}</h3>
        </div>
        <p class="smgp-restore__landing-zone-desc">{{ $t('restore.zone_user_desc') }}</p>
        <div class="smgp-restore__zone-table">
          <table class="table">
            <thead>
              <tr>
                <th>{{ $t('restore.col_filename') }}</th>
                <th>{{ $t('restore.col_time') }}</th>
                <th>{{ $t('restore.col_size') }}</th>
                <th>{{ $t('restore.col_download') }}</th>
                <th>{{ $t('restore.col_restore') }}</th>
                <th>{{ $t('restore.col_status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(b, i) in zonesData?.user_backups || []" :key="`ub-${i}-${b.filename}`">
                <td><strong>{{ b.filename }}</strong></td>
                <td>{{ b.time ? new Date(b.time * 1000).toLocaleString() : '—' }}</td>
                <td>{{ formatBytes(b.size) }}</td>
                <td><a :href="b.downloadurl" target="_blank" class="btn btn-sm btn-outline-success">{{ $t('restore.col_download') }}</a></td>
                <td><button class="btn btn-sm btn-success" @click="restoreFromArea(b)">{{ $t('restore.col_restore') }}</button></td>
                <td class="text-muted">{{ statusLabel(b.status) }}</td>
              </tr>
              <tr v-if="!(zonesData?.user_backups || []).length">
                <td colspan="6" class="text-center text-muted small">{{ $t('restore.zone_empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ─── Restorations in progress ────────────────────── -->
      <div class="smgp-restore__landing-zone">
        <div class="smgp-restore__landing-zone-title">
          <i class="bi bi-arrow-repeat" />
          <h3>{{ $t('restore.zone_progress_title') }}</h3>
        </div>
        <div class="smgp-restore__zone-table">
          <table class="table">
            <thead>
              <tr>
                <th>{{ $t('restore.col_course') }}</th>
                <th>{{ $t('restore.col_time') }}</th>
                <th>{{ $t('restore.col_status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in zonesData?.in_progress || []" :key="`ip-${i}-${r.time}`">
                <td><strong>{{ r.coursename }}</strong></td>
                <td>{{ r.time ? new Date(r.time * 1000).toLocaleString() : '—' }}</td>
                <td class="text-muted">{{ statusLabel(r.status) }}</td>
              </tr>
              <tr v-if="!(zonesData?.in_progress || []).length">
                <td colspan="3" class="text-center text-muted small">{{ $t('restore.zone_empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Step indicator (only after landing) -->
    <ol v-if="step >= 1" class="smgp-restore__steps">
      <li
        v-for="(stepname, idx) in stepNames"
        :key="idx"
        :class="{
          'is-current': step === idx + 1,
          'is-done': step > idx + 1,
        }"
      >
        <span class="smgp-restore__step-num">{{ idx + 1 }}.</span>
        <span class="smgp-restore__step-name">{{ stepname }}</span>
      </li>
    </ol>

    <h1 v-if="step >= 1" class="smgp-restore__title">{{ $t('restore.title') }}</h1>

    <!-- ─── Step 1: Confirm ──────────────────────────────────── -->
    <!-- No outer section wrapper / heading here — the cards stand on
         their own. The green left accent comes from the
         smgp-restore__step1 container. -->
    <div v-if="step === 1" class="smgp-restore__step1">
      <div v-if="!prepareResult" class="text-muted">{{ $t('restore.executing') }}</div>

      <div v-else-if="prepareResult.success">
        <!-- Card 1: Backup details ─────────────────────────────────── -->
        <div class="smgp-restore__confirm-card">
          <h3>{{ $t('restore.backup_details') }}</h3>
          <dl class="smgp-restore__confirm-grid">
            <dt>{{ $t('restore.confirm_type') }}</dt>
            <dd>{{ prepareResult.backup_type || '—' }}</dd>

            <dt>{{ $t('restore.confirm_format') }}</dt>
            <dd>{{ prepareResult.backup_format || '—' }}</dd>

            <dt>{{ $t('restore.confirm_mode') }}</dt>
            <dd>{{ prepareResult.backup_mode || '—' }}</dd>

            <dt>{{ $t('restore.backup_date') }}</dt>
            <dd>{{ prepareResult.backup_date ? new Date(prepareResult.backup_date * 1000).toLocaleString() : '—' }}</dd>

            <dt>{{ $t('restore.confirm_moodle_version') }}</dt>
            <dd>
              <div>{{ prepareResult.moodle_release || '—' }}</div>
              <div v-if="prepareResult.moodle_version" class="smgp-restore__confirm-subtext">[{{ prepareResult.moodle_version }}]</div>
            </dd>

            <dt>{{ $t('restore.confirm_backup_release') }}</dt>
            <dd>
              <div>{{ prepareResult.backup_release || '—' }}</div>
              <div v-if="prepareResult.backup_version" class="smgp-restore__confirm-subtext">[{{ prepareResult.backup_version }}]</div>
            </dd>

            <dt>{{ $t('restore.confirm_original_url') }}</dt>
            <dd>
              <div>{{ prepareResult.original_wwwroot || '—' }}</div>
              <div v-if="prepareResult.original_site_hash" class="smgp-restore__confirm-subtext">[{{ prepareResult.original_site_hash }}]</div>
            </dd>
          </dl>
        </div>

        <!-- Card 2: Included content checklist ────────────────────── -->
        <div v-if="includedBadges.length" class="smgp-restore__confirm-card">
          <h3>{{ $t('restore.included_content') }}</h3>
          <table class="smgp-restore__confirm-checklist">
            <tbody>
              <tr v-for="b in includedBadges" :key="b.name">
                <td class="smgp-restore__confirm-checklist-label">{{ $t('restore.included.' + b.name) }}</td>
                <td class="smgp-restore__confirm-checklist-value">
                  <i
                    :class="['bi', b.enabled ? 'bi-check-lg' : 'bi-x-lg']"
                    :style="{ color: b.enabled ? '#10b981' : '#dc2626' }"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Card 3: Course details ────────────────────────────────── -->
        <div class="smgp-restore__confirm-card">
          <h3>{{ $t('restore.confirm_course_details') }}</h3>
          <dl class="smgp-restore__confirm-grid">
            <dt>{{ $t('restore.confirm_title') }}</dt>
            <dd>{{ prepareResult.original_fullname || '—' }}</dd>
            <dt>{{ $t('restore.confirm_original_id') }}</dt>
            <dd>{{ prepareResult.original_courseid || '—' }}</dd>
          </dl>

          <h4 class="smgp-restore__confirm-subheading">{{ $t('restore.confirm_course_sections') }}</h4>

          <div v-if="loadingSchema" class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-success" role="status" />
          </div>

          <div v-else-if="structure.length" class="smgp-restore__confirm-section-list">
            <div
              v-for="sec in structure"
              :key="sec.sectionKey"
              class="smgp-restore__confirm-section"
            >
              <div class="smgp-restore__confirm-section-header">
                <strong>{{ $t('restore.confirm_section_label') }} {{ sec.section_number }}</strong>
                <span v-if="sec.name && sec.name !== sec.origName">: {{ sec.name }}</span>
                <span v-else-if="sec.name" class="ms-1">: {{ sec.name }}</span>
                <em class="smgp-restore__confirm-section-note">{{ $t('restore.confirm_section_note') }}</em>
              </div>

              <div v-if="sec.activities.length" class="smgp-restore__confirm-activities">
                <div class="smgp-restore__confirm-activities-label">
                  {{ $t('restore.confirm_activities_label') }}
                </div>
                <table class="smgp-restore__confirm-activities-table">
                  <thead>
                    <tr>
                      <th>{{ $t('restore.confirm_col_module') }}</th>
                      <th>{{ $t('restore.confirm_col_title') }}</th>
                      <th class="text-end">{{ $t('restore.confirm_col_userinfo') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="act in sec.activities" :key="act.actKey">
                      <td>
                        <i :class="['bi', modIcon(act.modname), 'smgp-restore__confirm-mod-icon']" />
                        {{ modLabel(act.modname) }}
                      </td>
                      <td><strong>{{ act.name }}</strong></td>
                      <td class="text-end">
                        <i
                          :class="['bi', act.userinfoAvailable ? 'bi-check-lg' : 'bi-x-lg']"
                          :style="{ color: act.userinfoAvailable ? '#10b981' : '#dc2626' }"
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <p v-else class="text-muted small">{{ $t('restore.no_activities') }}</p>
        </div>

        <!-- Card 4: SharePoint file table (only when handoff brought one) -->
        <div v-if="sharepointScan && sharepointScanFiles.length" class="smgp-restore__confirm-card">
          <h3>{{ $t('restore.sharepoint_extras_title') }}</h3>
          <p class="text-muted small mb-3">{{ $t('restore.sharepoint_extras_desc') }}</p>
          <div class="smgp-restore__sp-table">
            <table class="table">
              <thead>
                <tr>
                  <th style="width:48px"></th>
                  <th>{{ $t('courseloader.file_col') }}</th>
                  <th class="text-center" style="width:120px">{{ $t('courseloader.type_col') }}</th>
                  <th class="text-center" style="width:120px">{{ $t('courseloader.size_col') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(f, i) in sharepointScanFiles" :key="`${f.type}-${i}-${f.name}`">
                  <td class="smgp-restore__sp-icon">
                    <i :class="['bi', f.icon]" :style="{ color: f.colorHex }" />
                  </td>
                  <td>{{ f.name }}</td>
                  <td class="text-center">
                    <span class="smgp-restore__sp-badge" :style="{ background: f.badgeBg, color: f.colorHex }">
                      {{ f.label }}
                    </span>
                  </td>
                  <td class="text-center text-muted">{{ formatBytes(f.size) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-if="prepareResult && prepareResult.success" class="smgp-restore__confirm-actions">
        <button class="btn smgp-restore__back-btn" @click="cancelToLanding">← {{ $t('restore.back') }}</button>
        <button class="btn btn-success smgp-restore__continue" @click="step = 2">
          {{ $t('restore.continue') }}
        </button>
      </div>
    </div>

    <!-- ─── Step 2: Destination ──────────────────────────────── -->
    <!-- Step 2 has no outer section wrapper. Two independent destination
         cards: pick "new course" or "existing course" by clicking the
         Continuar button inside whichever card. -->
    <div v-else-if="step === 2" class="smgp-restore__step1">

      <!-- ────── Card 1: Restore as new course ────── -->
      <div class="smgp-restore__confirm-card">
        <h3>{{ $t('restore.dest_new_title') }}</h3>

        <div class="smgp-restore__dest-row">
          <span class="smgp-restore__dest-row-label">{{ $t('restore.dest_new_label') }}</span>
          <input
            v-model="destination.target"
            type="radio"
            value="new"
            class="smgp-restore__dest-radio"
          >
        </div>

        <div class="smgp-restore__dest-section-header">
          <h4>{{ $t('restore.dest_pick_company') }}</h4>
          <div class="smgp-restore__dest-search">
            <input
              v-model="companyFilter"
              type="text"
              class="form-control form-control-sm"
              :placeholder="$t('restore.dest_pick_company') + '...'"
            >
            <i class="bi bi-search smgp-restore__dest-search-icon" />
          </div>
        </div>

        <div class="smgp-restore__company-table smgp-restore__dest-table">
          <table class="table">
            <thead>
              <tr>
                <th class="smgp-restore__toggle-cell" style="width:60px">
                  <span class="smgp-restore__toggle-wrap form-switch">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="allFilteredSelected"
                      :indeterminate.prop="someFilteredSelected && !allFilteredSelected"
                      @change="toggleAllCompanies(($event.target as HTMLInputElement).checked)"
                    >
                  </span>
                </th>
                <th>{{ $t('courseloader.company_col') }}</th>
                <th>{{ $t('courseloader.shortname_col') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in filteredCompanies" :key="c.id">
                <td class="smgp-restore__toggle-cell">
                  <span class="smgp-restore__toggle-wrap form-switch">
                    <input
                      v-model="selectedCompanyIds"
                      class="form-check-input"
                      type="checkbox"
                      :value="c.id"
                    >
                  </span>
                </td>
                <td><strong>{{ c.name }}</strong></td>
                <td class="text-muted">{{ c.shortname }}</td>
              </tr>
              <tr v-if="!filteredCompanies.length">
                <td colspan="3" class="text-center text-muted small">{{ $t('courseloader.companies_empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="smgp-restore__confirm-actions">
          <button
            class="btn btn-success smgp-restore__continue"
            @click="continueAsNew"
          >
            {{ $t('restore.continue') }}
          </button>
        </div>
      </div>

      <!-- ────── Card 2: Restore into existing course ────── -->
      <div class="smgp-restore__confirm-card">
        <h3>{{ $t('restore.dest_existing_title') }}</h3>

        <div class="smgp-restore__dest-row">
          <span class="smgp-restore__dest-row-label">{{ $t('restore.dest_merge_label') }}</span>
          <input
            v-model="destination.target"
            type="radio"
            value="existing_merge"
            class="smgp-restore__dest-radio"
          >
        </div>
        <div class="smgp-restore__dest-row">
          <span class="smgp-restore__dest-row-label">{{ $t('restore.dest_delete_label') }}</span>
          <input
            v-model="destination.target"
            type="radio"
            value="existing_delete"
            class="smgp-restore__dest-radio"
          >
        </div>

        <div class="smgp-restore__dest-section-header">
          <h4>{{ $t('restore.dest_pick_course') }}</h4>
          <div class="smgp-restore__dest-search">
            <input
              v-model="targetCourseSearch"
              type="text"
              class="form-control form-control-sm"
              :placeholder="$t('restore.dest_pick_course') + '...'"
              @input="searchRestoreCourses"
            >
            <i class="bi bi-search smgp-restore__dest-search-icon" />
          </div>
        </div>

        <div class="smgp-restore__company-table smgp-restore__dest-table">
          <table class="table">
            <thead>
              <tr>
                <th style="width:60px"></th>
                <th>{{ $t('restore.dest_course_short') }}</th>
                <th>{{ $t('restore.dest_course_full') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="rc in restoreCourses"
                :key="rc.id"
                :class="{ 'is-selected-row': destination.targetCourseId === rc.id }"
                @click="destination.targetCourseId = rc.id"
              >
                <td class="text-center">
                  <input
                    v-model.number="destination.targetCourseId"
                    type="radio"
                    :value="rc.id"
                    class="smgp-restore__dest-radio"
                  >
                </td>
                <td><strong>{{ rc.shortname }}</strong></td>
                <td class="text-muted">{{ rc.fullname }}</td>
              </tr>
              <tr v-if="!restoreCourses.length">
                <td colspan="3" class="text-center text-muted small">{{ $t('restore.dest_courses_empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="restoreCourses.length >= 50" class="text-center text-muted small mt-2">
          {{ $t('restore.dest_too_many_results') }}
        </p>

        <div class="smgp-restore__confirm-actions">
          <button
            class="btn btn-success smgp-restore__continue"
            :disabled="!destination.targetCourseId"
            @click="continueAsExisting"
          >
            {{ $t('restore.continue') }}
          </button>
        </div>
      </div>

      <div class="smgp-restore__confirm-actions">
        <button class="btn smgp-restore__back-btn" @click="step = 1">← {{ $t('restore.back') }}</button>
      </div>
    </div>

    <!-- ─── Step 3: Settings ─────────────────────────────────── -->
    <div v-else-if="step === 3" class="smgp-restore__step1">
      <div class="smgp-restore__confirm-card">
        <h3>{{ $t('restore.settings_title') }}</h3>

        <div v-if="loadingSettings" class="text-center py-4">
          <div class="spinner-border text-success" role="status" />
        </div>
        <div v-else class="smgp-restore__settings-grid">
          <div
            v-for="(setting, idx) in settingsList"
            :key="setting.name"
            class="smgp-restore__settings-row"
          >
            <span class="smgp-restore__settings-num">{{ idx + 1 }}</span>
            <label :for="`set-${setting.name}`" class="smgp-restore__settings-label">
              {{ setting.label || setting.name }}
            </label>
            <select
              v-if="setting.type === 'select'"
              :id="`set-${setting.name}`"
              class="form-select form-select-sm smgp-restore__settings-select"
              :value="customSettings[setting.name] ?? setting.value"
              :disabled="setting.locked"
              @change="updateSetting(setting.name, ($event.target as HTMLSelectElement).value)"
            >
              <option value="1">{{ $t('restore.yes') }}</option>
              <option value="0">{{ $t('restore.no') }}</option>
            </select>
            <button
              v-else-if="isBinarySetting(setting)"
              type="button"
              class="smgp-restore__settings-toggle"
              :class="{ 'is-on': (customSettings[setting.name] ?? setting.value) == '1' }"
              @click="updateSetting(setting.name, (customSettings[setting.name] ?? setting.value) == '1' ? '0' : '1')"
            >
              <i v-if="(customSettings[setting.name] ?? setting.value) == '1'" class="bi bi-check-lg" />
              <i v-else class="bi bi-x-lg" />
            </button>
            <input
              v-else
              :id="`set-${setting.name}`"
              type="text"
              class="form-control form-control-sm smgp-restore__settings-text"
              :value="customSettings[setting.name] ?? setting.value"
              :disabled="setting.locked"
              @input="updateSetting(setting.name, ($event.target as HTMLInputElement).value)"
            >
          </div>
        </div>
      </div>

      <div class="smgp-restore__confirm-actions">
        <button class="btn smgp-restore__back-btn" @click="step = 2">← {{ $t('restore.back') }}</button>
        <button class="btn btn-success smgp-restore__continue" @click="loadSchema">
          {{ $t('restore.next') }}
        </button>
      </div>
    </div>

    <!-- ─── Step 4: Schema (SmartMind metadata + structure editor) ─── -->
    <div v-else-if="step === 4" class="smgp-restore__step1">

      <!-- SmartMind metadata + objectives -->
      <div class="smgp-restore__confirm-card">
        <h3><i class="bi bi-info-square smgp-restore__card-icon" /> {{ $t('restore.course_info') }}</h3>
        <div class="smgp-restore__struct-course-fields">
          <div class="smgp-restore__struct-course-field">
            <label><i class="bi bi-card-heading" /> {{ $t('restore.fullname') }} <i class="bi bi-info-circle smgp-restore__tip" :title="$t('restore.tip_fullname')" /></label>
            <input v-model="destination.fullname" type="text" class="form-control">
          </div>
          <div class="smgp-restore__struct-course-field">
            <label><i class="bi bi-tag" /> {{ $t('restore.shortname') }} <i class="bi bi-info-circle smgp-restore__tip" :title="$t('restore.tip_shortname')" /></label>
            <input v-model="destination.shortname" type="text" class="form-control">
          </div>
          <div class="smgp-restore__struct-course-field">
            <label><i class="bi bi-calendar-event" /> {{ $t('restore.startdate') }} <i class="bi bi-info-circle smgp-restore__tip" :title="$t('restore.tip_startdate')" /></label>
            <input v-model="destination.startdate" type="date" class="form-control">
          </div>
        </div>
        <MetadataFields
          :model-value="smgpMeta"
          :categories="smgpCatalogueCategories"
          :rich="true"
          @update:model-value="onSmgpMetaUpdate"
        />
        <ObjectivesEditor v-model="smgpObjectives" />
      </div>

      <!-- Course structure -->
      <div class="smgp-restore__confirm-card">
        <h3><i class="bi bi-stack smgp-restore__card-icon smgp-restore__card-icon--purple" /> {{ $t('restore.course_structure') }}</h3>

        <div v-if="loadingSchema" class="text-center py-4">
          <div class="spinner-border text-success" role="status" />
        </div>

        <div v-else class="smgp-restore__struct-list">
          <div
            v-for="sec in structure"
            :key="sec.sectionKey"
            class="smgp-restore__struct-section"
            :class="{ 'is-drop-target': dragOverSectionKey === sec.sectionKey, 'is-disabled': !sec.included }"
            @dragover="onSectionDragOver($event, sec.sectionKey)"
            @dragleave="onSectionDragLeave(sec.sectionKey)"
            @drop="onSectionDrop($event, sec)"
          >
            <div class="smgp-restore__struct-section-header">
              <i class="bi bi-list smgp-restore__struct-handle" />
              <span class="form-switch smgp-restore__struct-toggle">
                <input v-model="sec.included" class="form-check-input" type="checkbox">
              </span>
              <input
                v-model="sec.name"
                type="text"
                class="smgp-restore__struct-name-input"
                :placeholder="sectionDisplayName(sec)"
              >
              <span class="smgp-restore__struct-count">
                {{ sec.activities.length + sec.spExtras.length }} {{ activityCountLabel(sec.activities.length + sec.spExtras.length) }}
              </span>
              <button class="btn btn-sm smgp-restore__struct-remove" :title="$t('restore.remove')" @click="confirmRemoveSection(sec)">
                <i class="bi bi-x-lg" />
              </button>
            </div>

            <div class="smgp-restore__struct-activities">
              <div
                v-for="act in sec.activities"
                :key="act.actKey"
                class="smgp-restore__struct-activity"
              >
                <i class="bi bi-list smgp-restore__struct-handle" />
                <span class="smgp-restore__struct-icon-wrap">
                  <i :class="['bi', MOD_META[act.modname]?.icon || 'bi-puzzle']" />
                </span>
                <div class="smgp-restore__struct-activity-info">
                  <input
                    v-model="act.name"
                    type="text"
                    class="smgp-restore__struct-act-name"
                    :placeholder="act.origName"
                  >
                  <span class="smgp-restore__struct-modtype">
                    {{ MOD_META[act.modname]?.label || act.modname }}
                    <span v-if="act.deferred" class="smgp-restore__deferred-badge" :title="$t('restore.activity_deferred_hint')">
                      <i class="bi bi-exclamation-triangle-fill" /> {{ $t('restore.deferred_badge') }}
                    </span>
                  </span>
                </div>
                <span class="form-switch smgp-restore__struct-toggle">
                  <input v-model="act.included" class="form-check-input" type="checkbox">
                </span>
                <button
                  v-if="act.cmid === 0"
                  class="btn btn-sm smgp-restore__struct-remove"
                  :title="$t('restore.edit')"
                  @click="openEditModal(sec, act)"
                >
                  <i class="bi bi-pencil" />
                </button>
                <button class="btn btn-sm smgp-restore__struct-remove" :title="$t('restore.remove')" @click="confirmRemoveActivity(sec, act)">
                  <i class="bi bi-x-lg" />
                </button>
              </div>

              <!-- SharePoint extras dropped into this section -->
              <div
                v-for="sx in sec.spExtras"
                :key="sx.id"
                class="smgp-restore__struct-activity smgp-restore__struct-activity--sp"
              >
                <i class="bi bi-list smgp-restore__struct-handle" />
                <span class="smgp-restore__struct-icon-wrap" :style="{ background: sx.badgeBg, color: sx.colorHex }">
                  <i :class="['bi', sx.icon]" />
                </span>
                <div class="smgp-restore__struct-activity-info">
                  <strong>{{ sx.name }}</strong>
                  <span class="smgp-restore__struct-modtype">{{ sx.label }} · {{ formatBytes(sx.size) }}</span>
                </div>
                <button class="btn btn-sm smgp-restore__struct-remove" @click="removeSpExtra(sec, sx.id)">
                  <i class="bi bi-x-lg" />
                </button>
              </div>

              <button class="smgp-restore__struct-add-act" @click="toggleActPicker(sec.sectionKey)">
                <i class="bi bi-plus" /> {{ $t('restore.add_activity') }}
              </button>
              <AddActivityPicker
                v-if="openActPickerKey === sec.sectionKey"
                @select="onActPickerSelect($event, sec)"
              />
            </div>
          </div>

          <button class="smgp-restore__struct-add-sec" @click="addSection">
            <i class="bi bi-plus-circle" /> {{ $t('restore.add_section') }}
          </button>
        </div>
      </div>

      <!-- Confirm delete (section or activity) -->
      <DeleteActivityModal
        :open="deleteTarget !== null"
        :activity-name="deleteTarget?.name ?? ''"
        @close="deleteTarget = null"
        @confirm="onDeleteConfirm"
      />

      <!-- Unified add/edit activity modal -->
      <AddActivityModal
        :open="addModalState.open"
        :mode="addModalState.mode"
        :layout="addModalState.layout"
        :sectionnum="0"
        :editing="addModalState.editing"
        :file-accept="addModalState.fileAccept"
        :initial="addModalState.initial"
        @close="addModalState.open = false"
        @save="onAddModalSave"
      />

      <!-- SharePoint extras palette (only when restoring from a SP scan) -->
      <div v-if="sharepointScan && availableSpExtras.length" class="smgp-restore__confirm-card">
        <h3><i class="bi bi-cloud-download smgp-restore__card-icon smgp-restore__card-icon--blue" /> {{ $t('restore.sharepoint_extras_title') }}</h3>
        <p class="text-muted small mb-3">{{ $t('restore.sharepoint_extras_drag_hint') }}</p>
        <div class="smgp-restore__sp-palette">
          <div
            v-for="f in availableSpExtras"
            :key="f.id"
            class="smgp-restore__sp-chip"
            draggable="true"
            @dragstart="onSpExtraDragStart($event, f)"
          >
            <span class="smgp-restore__struct-icon-wrap" :style="{ background: f.badgeBg, color: f.colorHex }">
              <i :class="['bi', f.icon]" />
            </span>
            <div class="smgp-restore__sp-chip-info">
              <strong>{{ f.name }}</strong>
              <span>{{ f.label }} · {{ formatBytes(f.size) }}</span>
            </div>
            <i class="bi bi-grip-vertical smgp-restore__struct-handle" />
          </div>
        </div>
      </div>

      <div class="smgp-restore__confirm-actions">
        <button class="btn smgp-restore__back-btn" @click="step = 3">← {{ $t('restore.back') }}</button>
        <button class="btn btn-success smgp-restore__continue" @click="step = 5">
          {{ $t('restore.next') }}
        </button>
      </div>
    </div>

    <!-- ─── Step 5: Review ───────────────────────────────────── -->
    <div v-else-if="step === 5" class="smgp-restore__step1">

      <!-- Course information (read-only mirror of step 4 top card) -->
      <div class="smgp-restore__confirm-card">
        <h3><i class="bi bi-info-square smgp-restore__card-icon" /> {{ $t('restore.course_info') }}</h3>

        <div class="smgp-restore__review-grid">
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-card-heading" /> {{ $t('restore.fullname') }}</span>
            <span class="smgp-restore__review-value">{{ destination.fullname || '—' }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-tag" /> {{ $t('restore.shortname') }}</span>
            <span class="smgp-restore__review-value">{{ destination.shortname || '—' }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-calendar-event" /> {{ $t('restore.startdate') }}</span>
            <span class="smgp-restore__review-value">{{ destination.startdate || '—' }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-clock" /> {{ $t('editor.duration_hours') }}</span>
            <span class="smgp-restore__review-value">{{ smgpMeta.duration_hours || 0 }} h</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-bar-chart-steps" /> {{ $t('editor.level') }}</span>
            <span class="smgp-restore__review-value">{{ reviewLevelLabel }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-patch-check" /> {{ $t('editor.completion_pct') }}</span>
            <span class="smgp-restore__review-value">{{ smgpMeta.completion_percentage || 0 }}%</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-bookmark" /> {{ $t('editor.category') }}</span>
            <span class="smgp-restore__review-value">{{ reviewCategoryName }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-upc-scan" /> {{ $t('editor.smartmind_code') }}</span>
            <span class="smgp-restore__review-value">{{ smgpMeta.smartmind_code || '—' }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-file-earmark-code" /> {{ $t('editor.sepe_code') }}</span>
            <span class="smgp-restore__review-value">{{ smgpMeta.sepe_code || '—' }}</span>
          </div>
        </div>

        <div class="smgp-restore__review-rich">
          <h4><i class="bi bi-file-text" /> {{ $t('editor.description') }}</h4>
          <div v-if="smgpMeta.description" class="smgp-restore__review-rich-body" v-html="smgpMeta.description" />
          <p v-else class="text-muted small mb-0">—</p>
        </div>

        <div class="smgp-restore__review-rich">
          <h4><i class="bi bi-list-check" /> {{ $t('editor.objectives') }}</h4>
          <ol v-if="smgpObjectives.length" class="smgp-restore__review-obj">
            <li v-for="(obj, i) in smgpObjectives" :key="i">{{ obj }}</li>
          </ol>
          <p v-else class="text-muted small mb-0">—</p>
        </div>
      </div>

      <!-- Course structure (read-only mirror of step 4 structure card) -->
      <div class="smgp-restore__confirm-card">
        <h3><i class="bi bi-stack smgp-restore__card-icon" /> {{ $t('restore.course_structure') }}</h3>

        <div class="smgp-restore__struct-list smgp-restore__struct-list--readonly">
          <div
            v-for="sec in structure"
            :key="sec.sectionKey"
            class="smgp-restore__struct-section"
            :class="{ 'is-disabled': !sec.included }"
          >
            <div class="smgp-restore__struct-section-header">
              <i class="bi bi-layers smgp-restore__struct-handle" />
              <i
                :class="['bi', sec.included ? 'bi-check-circle-fill' : 'bi-x-circle-fill', 'smgp-restore__review-state']"
                :style="{ color: sec.included ? '#10b981' : '#cbd5e1' }"
              />
              <strong class="smgp-restore__struct-name-static">
                {{ sec.name || sectionDisplayName(sec) }}
              </strong>
              <span class="smgp-restore__struct-count">
                {{ sec.activities.length + sec.spExtras.length }} {{ activityCountLabel(sec.activities.length + sec.spExtras.length) }}
              </span>
            </div>

            <div class="smgp-restore__struct-activities">
              <div
                v-for="act in sec.activities"
                :key="act.actKey"
                class="smgp-restore__struct-activity"
                :class="{ 'is-disabled': !act.included }"
              >
                <i
                  :class="['bi', act.included ? 'bi-check-circle-fill' : 'bi-x-circle-fill', 'smgp-restore__review-state']"
                  :style="{ color: act.included ? '#10b981' : '#cbd5e1' }"
                />
                <span class="smgp-restore__struct-icon-wrap">
                  <i :class="['bi', MOD_META[act.modname]?.icon || 'bi-puzzle']" />
                </span>
                <div class="smgp-restore__struct-activity-info">
                  <strong>{{ act.name || act.origName || '—' }}</strong>
                  <span class="smgp-restore__struct-modtype">{{ MOD_META[act.modname]?.label || act.modname }}</span>
                </div>
              </div>

              <div
                v-for="sx in sec.spExtras"
                :key="sx.id"
                class="smgp-restore__struct-activity smgp-restore__struct-activity--sp"
              >
                <i class="bi bi-cloud-download-fill smgp-restore__review-state" style="color: #2563eb" />
                <span class="smgp-restore__struct-icon-wrap" :style="{ background: sx.badgeBg, color: sx.colorHex }">
                  <i :class="['bi', sx.icon]" />
                </span>
                <div class="smgp-restore__struct-activity-info">
                  <strong>{{ sx.name }}</strong>
                  <span class="smgp-restore__struct-modtype">{{ sx.label }} · {{ formatBytes(sx.size) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="smgp-restore__confirm-actions">
        <button class="btn smgp-restore__back-btn" @click="step = 4">← {{ $t('restore.back') }}</button>
        <button class="btn btn-success smgp-restore__continue" @click="executeRestore">
          <i class="bi bi-play-circle" /> {{ $t('restore.execute') }}
        </button>
      </div>
    </div>

    <!-- ─── Step 6: Process ──────────────────────────────────── -->
    <div v-else-if="step === 6" class="smgp-restore__step1">
      <div class="smgp-restore__confirm-card">
        <h3><i class="bi bi-arrow-repeat smgp-restore__card-icon" /> {{ $t('restore.executing') }}</h3>

        <div v-if="executing" class="smgp-restore__processing">
          <div class="smgp-restore__progress"><div class="smgp-restore__progress-bar" /></div>
          <p class="smgp-restore__processing-phase">
            <i class="bi bi-clock-history" /> {{ $t('restore.processing_phase') }}: <strong>{{ processingPhase }}</strong>
          </p>
          <p class="text-muted small mb-0">{{ $t('restore.elapsed') }}: {{ elapsedSeconds }}s</p>
        </div>

        <div v-else-if="executeError" class="alert alert-danger mb-0">
          <strong><i class="bi bi-exclamation-triangle-fill" /> {{ $t('restore.error') }}</strong>
          <p class="mb-2">{{ executeError }}</p>
          <button class="btn btn-sm btn-success" @click="executeRestore">
            <i class="bi bi-arrow-clockwise" /> {{ $t('restore.retry') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ─── Step 7: Complete ─────────────────────────────────── -->
    <div v-else-if="step === 7" class="smgp-restore__step1">
      <div class="smgp-restore__confirm-card">
        <h3><i class="bi bi-check-circle-fill smgp-restore__card-icon" style="color:#10b981" /> {{ $t('restore.success') }}</h3>
        <p class="text-muted small">{{ $t('restore.complete_desc') }}</p>

        <div v-if="executeResult" class="smgp-restore__review-grid">
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-card-heading" /> {{ $t('restore.fullname') }}</span>
            <span class="smgp-restore__review-value">{{ destination.fullname }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-tag" /> {{ $t('restore.shortname') }}</span>
            <span class="smgp-restore__review-value">{{ destination.shortname }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-bookmark" /> {{ $t('restore.category') }}</span>
            <span class="smgp-restore__review-value">{{ reviewCategoryName }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-building" /> {{ $t('restore.companies_assigned') }}</span>
            <span class="smgp-restore__review-value">{{ executeResult.companies_assigned ?? 0 }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-layers" /> {{ $t('restore.sections_restored') }}</span>
            <span class="smgp-restore__review-value">{{ executeResult.sections_restored ?? 0 }}</span>
          </div>
          <div class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-puzzle" /> {{ $t('restore.activities_restored') }}</span>
            <span class="smgp-restore__review-value">{{ executeResult.activities_restored ?? 0 }}</span>
          </div>
          <div v-if="(executeResult.sp_extras_applied ?? 0) > 0" class="smgp-restore__review-item">
            <span class="smgp-restore__review-label"><i class="bi bi-cloud-download" /> {{ $t('restore.apply_extras_success') }}</span>
            <span class="smgp-restore__review-value">{{ executeResult.sp_extras_applied }}</span>
          </div>
        </div>

        <div v-if="executeResult && (executeResult.deferred_failures ?? []).length" class="alert alert-warning mt-3 mb-0">
          <strong>{{ $t('restore.deferred_failures_title') }}</strong>
          <ul class="mb-0 mt-1 small">
            <li v-for="(df, i) in executeResult.deferred_failures" :key="i">
              <strong>{{ df.name }}</strong> <span class="text-muted">({{ df.modname }})</span>
              <span v-if="df.error"> — {{ df.error }}</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="smgp-restore__confirm-actions">
        <button class="btn smgp-restore__back-btn" @click="resetWizard">
          <i class="bi bi-arrow-repeat" /> {{ $t('restore.restore_another') }}
        </button>
        <NuxtLink
          v-if="executeResult"
          :to="`/courses/${executeResult.courseid}/landing`"
          class="btn btn-success smgp-restore__continue"
        >
          <i class="bi bi-box-arrow-up-right" /> {{ $t('restore.view_course') }}
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import ObjectivesEditor from '~/components/editor/ObjectivesEditor.vue'
import MetadataFields from '~/components/editor/MetadataFields.vue'
import AddActivityPicker from '~/components/course/AddActivityPicker.vue'
import AddActivityModal from '~/components/course/AddActivityModal.vue'
import DeleteActivityModal from '~/components/course/DeleteActivityModal.vue'
import { ACTIVITY_TYPE_GROUPS, type ActivityType } from '~/components/course/activityTypes'

definePageMeta({ middleware: ['auth'] })

const { call } = useMoodleAjax()

const stepNames = [
  'Confirm',
  'Destination',
  'Settings',
  'Schema',
  'Review',
  'Process',
  'Complete',
]

// step 0 = landing page (file picker), 1..7 = the actual wizard.
const step = ref(0)
const auth = useAuthStore()

// ────────────────────────────────────────────────────────────
// Landing page state — only used on direct visits to /admin/restore.
// SharePoint handoffs jump straight to step 1.
// ────────────────────────────────────────────────────────────
const landingFile = ref<File | null>(null)
const landingFileInput = ref<HTMLInputElement | null>(null)
const dropzoneDragover = ref(false)
const landingError = ref<string | null>(null)

function triggerLandingPicker() {
  landingFileInput.value?.click()
}
function onLandingFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    landingFile.value = target.files[0] ?? null
    landingError.value = null
  }
}
function onLandingDrop(e: DragEvent) {
  dropzoneDragover.value = false
  const dt = e.dataTransfer
  if (!dt || !dt.files.length) return
  const f = dt.files[0]
  if (!f) return
  if (!f.name.toLowerCase().endsWith('.mbz')) {
    landingError.value = 'Only .mbz files are accepted'
    return
  }
  landingFile.value = f
  landingError.value = null
}

async function uploadLandingFile() {
  if (!landingFile.value) {
    landingError.value = 'Required'
    return
  }
  uploading.value = true
  landingError.value = null

  // Step A: POST the file to our upload endpoint, which moves it into
  // Moodle's backup temp directory and returns the staged filename.
  const fd = new FormData()
  fd.append('mbz', landingFile.value)
  fd.append('sesskey', auth.sesskey)
  let uploadResult: { success: boolean; filename?: string; error?: string }
  try {
    const resp = await fetch(
      `${auth.wwwroot}/local/sm_graphics_plugin/pages/upload_mbz.php`,
      { method: 'POST', body: fd, credentials: 'include' },
    )
    uploadResult = await resp.json()
  } catch (e) {
    uploading.value = false
    landingError.value = String(e)
    return
  }
  if (!uploadResult.success || !uploadResult.filename) {
    uploading.value = false
    landingError.value = uploadResult.error || 'Upload failed'
    return
  }

  // Step B: call restore_prepare with the staged filename, then jump to
  // step 1 (Confirm).
  const result = await call<any>('local_sm_graphics_plugin_restore_prepare', {
    filename: uploadResult.filename,
    draftitemid: 0,
  })
  uploading.value = false
  if (result.error || !result.data?.success) {
    landingError.value = result.error || result.data?.error || 'Failed to prepare backup'
    return
  }
  prepareResult.value = result.data
  destination.fullname = result.data.original_fullname || ''
  destination.shortname = (result.data.original_shortname || '') + '_' + Date.now()
  step.value = 1
  // Pre-load the schema so the Confirm card can render the per-section
  // activity tables. Same data is reused on step 4 without a re-fetch.
  fetchSchema()
}

function cancelToLanding() {
  step.value = 0
  prepareResult.value = null
  landingFile.value = null
  if (landingFileInput.value) landingFileInput.value.value = ''
  loadZones()
}

// ────────────────────────────────────────────────────────────
// Landing zones — course backups, user private backups, in-progress
// ────────────────────────────────────────────────────────────
interface BackupRow {
  filename: string
  time: number
  size: number
  contextid: number
  courseid?: number
  coursename?: string
  downloadurl: string
  pathnamehash: string
  status: string
}
interface ZonesData {
  course_backups: BackupRow[]
  user_backups: BackupRow[]
  in_progress: Array<{ coursename: string; time: number; status: string }>
}
const zonesData = ref<ZonesData | null>(null)

async function loadZones() {
  const result = await call<ZonesData>('local_sm_graphics_plugin_list_restore_backups', {})
  if (!result.error && result.data) {
    zonesData.value = result.data
  }
}
loadZones()

// Defensive status renderer — translates `restore.status_<key>` when the
// key exists, otherwise just falls back to a humanised version of the
// raw value (handles stale backend responses while OPcache is still
// holding the old labels).
const { t, te } = useI18n()
function statusLabel(raw: string): string {
  if (!raw) return ''
  const key = 'restore.status_' + raw.replace(/\s+/g, '_')
  if (te(key)) return t(key)
  // Fallback: replace underscores/dashes with spaces and capitalise.
  return raw === '—' || raw === 'none' ? '—' : raw.replace(/_/g, ' ')
}

// Click "Restaurar" on a zone row → load the stored file into the
// wizard via restore_prepare(pathnamehash=...). Stays inside the SPA;
// no bounce to Moodle's native restorefile.php page. After prepare
// succeeds the wizard jumps to step 1 with the prepared backup info
// and the schema preview, exactly like the SharePoint handoff path.
async function restoreFromArea(b: BackupRow) {
  uploading.value = true
  landingError.value = null
  const result = await call<any>('local_sm_graphics_plugin_restore_prepare', {
    pathnamehash: b.pathnamehash,
  })
  uploading.value = false
  if (result.error || !result.data?.success) {
    landingError.value = result.error || result.data?.error || 'Failed to load backup file.'
    return
  }
  prepareResult.value = result.data
  destination.fullname = result.data.original_fullname || ''
  destination.shortname = (result.data.original_shortname || '') + '_' + Date.now()
  step.value = 1
  fetchSchema()
}

// ────────────────────────────────────────────────────────────
// Step 1 — confirm
// ────────────────────────────────────────────────────────────
const uploading = ref(false)
const prepareResult = ref<any>(null)

const includedBadges = computed(() => {
  return prepareResult.value?.backup_settings ?? []
})

// SharePoint scan handoff — when the courseloader hands the wizard a
// pre-scanned manifest, render its file table inside step 1's Confirm
// card so the admin sees the SCORMs / PDFs / evaluations the courseloader
// found.
interface SharepointScanFile { name: string; size: number }
interface SharepointScan {
  mbz?: SharepointScanFile[]
  scorm?: SharepointScanFile[]
  pdf?: SharepointScanFile[]
  documents?: SharepointScanFile[]
  evaluations_aiken?: SharepointScanFile[]
  evaluations_gift?: SharepointScanFile[]
}
const sharepointScan = ref<SharepointScan | null>(null)

const SP_TYPE_META: Record<string, { icon: string; colorHex: string; badgeBg: string; label: string }> = {
  mbz:               { icon: 'bi-file-earmark-zip',    colorHex: '#10b981', badgeBg: 'rgba(16, 185, 129, 0.12)', label: 'MBZ' },
  scorm:             { icon: 'bi-file-earmark-binary', colorHex: '#2563eb', badgeBg: 'rgba(37, 99, 235, 0.12)',  label: 'SCORM' },
  pdf:               { icon: 'bi-file-earmark-pdf',    colorHex: '#dc2626', badgeBg: 'rgba(220, 38, 38, 0.12)',  label: 'PDF' },
  documents:         { icon: 'bi-file-earmark-text',   colorHex: '#64748b', badgeBg: 'rgba(100, 116, 139, 0.12)', label: 'DOC' },
  evaluations_aiken: { icon: 'bi-list-check',          colorHex: '#d97706', badgeBg: 'rgba(217, 119, 6, 0.12)',  label: 'AIKEN' },
  evaluations_gift:  { icon: 'bi-pencil-square',       colorHex: '#7c3aed', badgeBg: 'rgba(124, 58, 237, 0.12)', label: 'GIFT' },
}

const sharepointScanFiles = computed(() => {
  const r = sharepointScan.value
  if (!r) return []
  const out: Array<{ type: string; name: string; size: number; icon: string; colorHex: string; badgeBg: string; label: string }> = []
  for (const type of Object.keys(SP_TYPE_META)) {
    const arr = (r as any)[type] as SharepointScanFile[] | undefined
    if (!Array.isArray(arr)) continue
    const meta = SP_TYPE_META[type]!
    for (const f of arr) {
      out.push({ type, name: String(f.name ?? ''), size: Number(f.size ?? 0), ...meta })
    }
  }
  return out
})

function formatBytes(bytes: number): string {
  if (!bytes || bytes <= 0) return '—'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let i = 0
  let n = bytes
  while (n >= 1024 && i < units.length - 1) {
    n /= 1024
    i++
  }
  return `${n < 10 ? n.toFixed(1) : Math.round(n)} ${units[i]}`
}

// ────────────────────────────────────────────────────────────
// Step 2 — destination + companies
// ────────────────────────────────────────────────────────────
interface CategoryRow { id: number; name: string; depth: number; label: string }
const moodleCategories = ref<CategoryRow[]>([])
const smgpCatalogueCategories = ref<Array<{ id: number; name: string }>>([])

const destination = reactive({
  fullname: '',
  shortname: '',
  categoryid: 1,
  startdate: '',
  target: 'new' as 'new' | 'existing_merge' | 'existing_delete',
  targetCourseId: 0,
})

interface RestoreCourseRow { id: number; fullname: string; shortname: string }
const targetCourseSearch = ref('')
const restoreCourses = ref<RestoreCourseRow[]>([])
let restoreCourseSearchTimer: ReturnType<typeof setTimeout> | null = null

function searchRestoreCourses() {
  if (restoreCourseSearchTimer) clearTimeout(restoreCourseSearchTimer)
  restoreCourseSearchTimer = setTimeout(async () => {
    const prevScroll = typeof window !== 'undefined' ? window.scrollY : 0
    const result = await call<RestoreCourseRow[]>(
      'local_sm_graphics_plugin_get_courses_for_restore',
      { search: targetCourseSearch.value, limit: 50 },
    )
    if (!result.error && Array.isArray(result.data)) {
      restoreCourses.value = result.data
    }
    if (typeof window !== 'undefined') {
      // Lock scroll across the layout reflow caused by N new rows.
      requestAnimationFrame(() => window.scrollTo({ top: prevScroll, behavior: 'instant' as ScrollBehavior }))
    }
  }, 200)
}

// Pre-load the first batch of courses when step 2 is shown so the
// "Restaurar en un curso existente" table isn't empty before the admin
// types anything.
watch(step, (s) => {
  if (s === 2 && restoreCourses.value.length === 0) {
    searchRestoreCourses()
  }
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior })
  }
})

// Confirm button handlers — each card sets the target mode and calls
// the same loadSettings() entry point.
function continueAsNew() {
  destination.target = 'new'
  destination.targetCourseId = 0
  loadSettings()
}
function continueAsExisting() {
  if (!destination.target.startsWith('existing_')) {
    destination.target = 'existing_merge'
  }
  if (!destination.targetCourseId) return
  loadSettings()
}

async function loadCategories() {
  const result = await call<CategoryRow[]>('local_sm_graphics_plugin_get_course_categories', {})
  if (!result.error && Array.isArray(result.data)) {
    moodleCategories.value = result.data
    if (!destination.categoryid && result.data[0]) {
      destination.categoryid = result.data[0].id
    }
  }
}
loadCategories()

async function loadSmgpCatalogueCategories() {
  const result = await call<{ smgp_categories?: Array<{ id: number; name: string }> }>(
    'local_sm_graphics_plugin_get_course_edit_data',
    { courseid: 0 },
  )
  if (!result.error && Array.isArray(result.data?.smgp_categories)) {
    smgpCatalogueCategories.value = result.data.smgp_categories
  }
}
loadSmgpCatalogueCategories()

const destinationCategoryName = computed(() => {
  const cat = moodleCategories.value.find(c => c.id === destination.categoryid)
  return cat ? cat.label : String(destination.categoryid)
})
const targetCourseSummary = computed(() => {
  const c = restoreCourses.value.find(rc => rc.id === destination.targetCourseId)
  return c ? `${c.fullname} (${c.shortname})` : '—'
})
void targetCourseSummary


// Companies picker state (same pattern as the courseloader page).
const SMGP_RESTORE_COMPANIES = 'smgp_restore_company_ids'
interface CompanyRow { id: number; name: string; shortname: string }
const companies = ref<CompanyRow[]>([])
const companyFilter = ref('')
const selectedCompanyIds = ref<number[]>(loadInitialCompanies())

function loadInitialCompanies(): number[] {
  if (typeof window === 'undefined') return []
  try {
    const raw = window.sessionStorage.getItem(SMGP_RESTORE_COMPANIES)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed.map((n: unknown) => Number(n)).filter(Boolean) : []
  } catch {
    return []
  }
}

watch(selectedCompanyIds, (ids) => {
  if (typeof window === 'undefined') return
  window.sessionStorage.setItem(SMGP_RESTORE_COMPANIES, JSON.stringify(ids))
}, { deep: true })

function normalize(s: string): string {
  return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}
const filteredCompanies = computed(() => {
  const term = normalize(companyFilter.value.trim())
  if (!term) return companies.value
  return companies.value.filter(c =>
    normalize(c.name).includes(term) || normalize(c.shortname).includes(term),
  )
})
const allFilteredSelected = computed(() =>
  filteredCompanies.value.length > 0 &&
  filteredCompanies.value.every(c => selectedCompanyIds.value.includes(c.id)),
)
const someFilteredSelected = computed(() =>
  filteredCompanies.value.some(c => selectedCompanyIds.value.includes(c.id)),
)
function toggleAllCompanies(checked: boolean) {
  const ids = filteredCompanies.value.map(c => c.id)
  if (checked) {
    selectedCompanyIds.value = Array.from(new Set([...selectedCompanyIds.value, ...ids]))
  } else {
    selectedCompanyIds.value = selectedCompanyIds.value.filter(id => !ids.includes(id))
  }
}
function companyName(id: number): string {
  return companies.value.find(c => c.id === id)?.name ?? `#${id}`
}

async function loadCompanies() {
  const result = await call<CompanyRow[]>('local_sm_graphics_plugin_get_company_stats', {})
  if (!result.error && Array.isArray(result.data)) {
    companies.value = result.data.map((c: any) => ({
      id: Number(c.id),
      name: String(c.name),
      shortname: String(c.shortname),
    }))
  }
}
loadCompanies()

// ────────────────────────────────────────────────────────────
// Step 3 — settings
// ────────────────────────────────────────────────────────────
interface SettingRow { name: string; label: string; type: string; value: string; visible: boolean; locked: boolean }
const loadingSettings = ref(false)
const settingsList = ref<SettingRow[]>([])
const customSettings = reactive<Record<string, string>>({})

async function loadSettings() {
  step.value = 3
  loadingSettings.value = true
  const result = await call<{ success: boolean; settings?: SettingRow[] }>(
    'local_sm_graphics_plugin_restore_get_settings',
    { backupid: prepareResult.value.backupid, categoryid: destination.categoryid },
  )
  loadingSettings.value = false
  if (!result.error && result.data?.success) {
    settingsList.value = result.data.settings ?? []
  }
}
function updateSetting(name: string, value: string) {
  customSettings[name] = value
}
function isBinarySetting(s: SettingRow): boolean {
  if (s.type === 'checkbox') return true
  const v = String(customSettings[s.name] ?? s.value ?? '').trim()
  return v === '0' || v === '1'
}
const settingOverrides = computed(() => {
  // Only show settings the user actually changed against the defaults.
  const out: Array<{ name: string; label: string; value: string }> = []
  for (const s of settingsList.value) {
    const override = customSettings[s.name]
    if (override !== undefined && override !== s.value) {
      out.push({ name: s.name, label: s.label || s.name, value: override })
    }
  }
  return out
})

// ────────────────────────────────────────────────────────────
// Step 4 — schema editor + SmartMind metadata
// ────────────────────────────────────────────────────────────
interface SchemaActivity {
  actKey: string
  cmid: number
  modname: string
  name: string
  origName: string
  included: boolean
  userinfo: boolean
  userinfoAvailable: boolean
  /** External URL for mod_url / Genially / Video layouts. */
  url?: string
  /** Draft file itemid staged via upload_activity_file.php. */
  draftitemid?: number
  /** Display name of the uploaded file (for the row UI). */
  filename?: string
  /** Rich-text body for mod_label / mod_page. */
  intro?: string
  /** `true` for types the wizard creates blank and the admin finishes
   *  in Moodle's native mod form after the restore. */
  deferred?: boolean
}
interface SpExtraDropped {
  id: string
  type: string
  name: string
  size: number
  icon: string
  colorHex: string
  badgeBg: string
  label: string
}
interface SchemaSection {
  sectionKey: string
  section_id: number
  section_number: number
  name: string
  origName: string
  included: boolean
  userinfo: boolean
  userinfoAvailable: boolean
  activities: SchemaActivity[]
  spExtras: SpExtraDropped[]
}

const loadingSchema = ref(false)
const structure = ref<SchemaSection[]>([])

async function fetchSchema() {
  loadingSchema.value = true
  const result = await call<{ success: boolean; sections?: any[] }>(
    'local_sm_graphics_plugin_restore_get_schema',
    { backupid: prepareResult.value.backupid, categoryid: destination.categoryid },
  )
  loadingSchema.value = false
  if (!result.error && result.data?.success) {
    structure.value = (result.data.sections ?? []).map((s: any): SchemaSection => ({
      sectionKey: s.section_key,
      section_id: s.section_id,
      section_number: s.section_number,
      name: s.title,
      origName: s.original_name,
      included: s.included,
      userinfo: false,
      userinfoAvailable: !!s.userinfo,
      activities: (s.activities ?? []).map((a: any): SchemaActivity => ({
        actKey: a.activity_key,
        cmid: a.cmid,
        modname: a.modname,
        name: a.name,
        origName: a.original_name,
        included: a.included,
        userinfo: false,
        userinfoAvailable: !!a.userinfo,
      })),
      spExtras: [],
    }))
  }
}

async function loadSchema() {
  step.value = 4
  if (!structure.value.length) {
    await fetchSchema()
  }
}

// SharePoint extras drag-drop into structure sections.
// The source list is `availableSpExtras` (everything from the scan that
// hasn't been dropped into a section yet). The dropped items live on
// `section.spExtras`. Backend wiring will pick these up later.
function activityCountLabel(n: number): string {
  return n === 1 ? 'actividad' : 'actividades'
}
const droppedSpExtraIds = computed(() => {
  const ids = new Set<string>()
  for (const sec of structure.value) {
    for (const x of sec.spExtras) ids.add(x.id)
  }
  return ids
})
const availableSpExtras = computed<SpExtraDropped[]>(() => {
  return sharepointScanFiles.value
    .map((f, i): SpExtraDropped => ({ id: `${f.type}::${f.name}::${i}`, ...f }))
    .filter(f => !droppedSpExtraIds.value.has(f.id))
})
const dragSpPayload = ref<SpExtraDropped | null>(null)
const dragOverSectionKey = ref<string | null>(null)
function onSpExtraDragStart(e: DragEvent, f: SpExtraDropped) {
  dragSpPayload.value = f
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', f.id)
  }
}
function onSectionDragOver(e: DragEvent, secKey: string) {
  if (!dragSpPayload.value) return
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
  dragOverSectionKey.value = secKey
}
function onSectionDragLeave(secKey: string) {
  if (dragOverSectionKey.value === secKey) dragOverSectionKey.value = null
}
function onSectionDrop(e: DragEvent, sec: SchemaSection) {
  e.preventDefault()
  dragOverSectionKey.value = null
  const payload = dragSpPayload.value
  dragSpPayload.value = null
  if (!payload) return
  if (sec.spExtras.some(x => x.id === payload.id)) return
  sec.spExtras.push(payload)
}
function removeSpExtra(sec: SchemaSection, id: string) {
  sec.spExtras = sec.spExtras.filter(x => x.id !== id)
}
function sectionDisplayName(sec: SchemaSection): string {
  if (sec.name && !/^\d+$/.test(sec.name.trim())) return sec.name
  return `Sección ${sec.section_number}`
}
function addSection() {
  const nextNum = (structure.value[structure.value.length - 1]?.section_number ?? -1) + 1
  structure.value.push({
    sectionKey: `new-${Date.now()}`,
    section_id: 0,
    section_number: nextNum,
    name: '',
    origName: '',
    included: true,
    userinfo: false,
    userinfoAvailable: false,
    activities: [],
    spExtras: [],
  })
}
function addActivity(sec: SchemaSection, modname = 'label', name = '', url = '') {
  sec.activities.push({
    actKey: `new-act-${Date.now()}`,
    cmid: 0,
    modname,
    name,
    origName: '',
    included: true,
    userinfo: false,
    userinfoAvailable: false,
    url,
  })
}

// ── Add-activity picker + unified create/edit modal ──────────────────
const openActPickerKey = ref<string | null>(null)
function toggleActPicker(secKey: string) {
  openActPickerKey.value = openActPickerKey.value === secKey ? null : secKey
}

interface AddModalState {
  open: boolean
  /** Header hint — 'genially' | 'video' for their URL presets, or the raw modname. */
  mode: string
  layout: 'url' | 'file' | 'body' | 'deferred'
  sectionKey: string
  /** The modname to persist on the activity when saved. */
  modname: string
  editing: boolean
  /** actKey of the activity being edited (empty when creating). */
  editKey: string
  fileAccept: string
  initial: {
    name?: string
    url?: string
    intro?: string
    draftitemid?: number
    filename?: string
  } | null
}
const addModalState = ref<AddModalState>({
  open: false,
  mode: 'genially',
  layout: 'url',
  sectionKey: '',
  modname: 'genially',
  editing: false,
  editKey: '',
  fileAccept: '',
  initial: null,
})

/** User picked a type from the inline picker under a section. */
function onActPickerSelect(type: ActivityType, sec: SchemaSection) {
  openActPickerKey.value = null
  const layout = type.layout ?? 'url'
  const modeHint = type.isGenially ? 'genially' : type.isVideo ? 'video' : type.mod
  // Persist modname as 'url' for plain URL resource pseudo-type 'url',
  // 'url' for Genially/Video (both stored as mod_url server-side), or
  // the raw modname otherwise.
  const persistMod = (type.isGenially || type.isVideo || type.mod === 'url') ? 'url' : type.mod
  addModalState.value = {
    open: true,
    mode: modeHint,
    layout,
    sectionKey: sec.sectionKey,
    modname: type.isGenially ? 'genially' : persistMod,
    editing: false,
    editKey: '',
    fileAccept: type.fileAccept ?? '',
    initial: null,
  }
}

/** User clicked the edit pencil on a new-activity row. */
function openEditModal(sec: SchemaSection, act: SchemaActivity) {
  // Locate the ActivityType entry to recover layout + fileAccept hints.
  let layout: 'url' | 'file' | 'body' | 'deferred' = 'url'
  let mode = act.modname
  let fileAccept = ''
  for (const group of ACTIVITY_TYPE_GROUPS) {
    for (const t of group.types) {
      const mnMatches = t.mod === act.modname ||
        (act.modname === 'genially' && t.isGenially) ||
        (act.modname === 'url' && (t.isVideo || t.mod === 'url'))
      if (mnMatches) {
        layout = t.layout ?? 'url'
        if (t.isGenially) mode = 'genially'
        else if (t.isVideo) mode = 'video'
        fileAccept = t.fileAccept ?? ''
        break
      }
    }
  }
  addModalState.value = {
    open: true,
    mode,
    layout,
    sectionKey: sec.sectionKey,
    modname: act.modname,
    editing: true,
    editKey: act.actKey,
    fileAccept,
    initial: {
      name: act.name,
      url: act.url,
      intro: act.intro,
      draftitemid: act.draftitemid,
      filename: act.filename,
    },
  }
}

function onAddModalSave(payload: {
  name: string
  url?: string
  intro?: string
  draftitemid?: number
  filename?: string
}) {
  const sec = structure.value.find(s => s.sectionKey === addModalState.value.sectionKey)
  if (!sec) {
    addModalState.value.open = false
    return
  }
  if (addModalState.value.editing) {
    const act = sec.activities.find(a => a.actKey === addModalState.value.editKey)
    if (act) {
      act.name = payload.name
      act.url = payload.url ?? act.url
      act.intro = payload.intro ?? act.intro
      act.draftitemid = payload.draftitemid ?? act.draftitemid
      act.filename = payload.filename ?? act.filename
    }
  } else {
    sec.activities.push({
      actKey: `new-act-${Date.now()}`,
      cmid: 0,
      modname: addModalState.value.modname,
      name: payload.name,
      origName: '',
      included: true,
      userinfo: false,
      userinfoAvailable: false,
      url: payload.url,
      intro: payload.intro,
      draftitemid: payload.draftitemid,
      filename: payload.filename,
      deferred: addModalState.value.layout === 'deferred',
    })
  }
  addModalState.value.open = false
}
function removeSection(sec: SchemaSection) {
  structure.value = structure.value.filter(s => s !== sec)
}
function removeActivity(sec: SchemaSection, act: SchemaActivity) {
  sec.activities = sec.activities.filter(a => a !== act)
}

// ── Confirm-delete modal (shared between sections and activities) ────
interface DeleteTarget {
  kind: 'section' | 'activity'
  name: string
  sec: SchemaSection
  act?: SchemaActivity
}
const deleteTarget = ref<DeleteTarget | null>(null)
function confirmRemoveSection(sec: SchemaSection) {
  deleteTarget.value = { kind: 'section', name: sec.name || sectionDisplayName(sec), sec }
}
function confirmRemoveActivity(sec: SchemaSection, act: SchemaActivity) {
  deleteTarget.value = { kind: 'activity', name: act.name || act.origName || '—', sec, act }
}
function onDeleteConfirm() {
  const t = deleteTarget.value
  if (!t) return
  if (t.kind === 'section') {
    removeSection(t.sec)
  } else if (t.act) {
    removeActivity(t.sec, t.act)
  }
  deleteTarget.value = null
}

// Module type → icon + label maps for the Confirm step's activity rows.
// Falls back to a generic "module" icon for unknown types.
const MOD_META: Record<string, { icon: string; label: string }> = {
  forum:      { icon: 'bi-chat-square-text', label: 'Foro' },
  scorm:      { icon: 'bi-box',              label: 'Paquete SCORM' },
  quiz:       { icon: 'bi-list-check',       label: 'Cuestionario' },
  assign:     { icon: 'bi-clipboard-check',  label: 'Tarea' },
  resource:   { icon: 'bi-file-earmark',     label: 'Archivo' },
  url:        { icon: 'bi-link-45deg',       label: 'URL' },
  page:       { icon: 'bi-file-earmark-text', label: 'Página' },
  book:       { icon: 'bi-book',             label: 'Libro' },
  folder:     { icon: 'bi-folder',           label: 'Carpeta' },
  label:      { icon: 'bi-card-text',        label: 'Etiqueta' },
  glossary:   { icon: 'bi-journal-text',     label: 'Glosario' },
  wiki:       { icon: 'bi-card-list',        label: 'Wiki' },
  choice:     { icon: 'bi-check2-square',    label: 'Encuesta' },
  feedback:   { icon: 'bi-chat-dots',        label: 'Retroalimentación' },
  lesson:     { icon: 'bi-mortarboard',      label: 'Lección' },
  workshop:   { icon: 'bi-people-fill',      label: 'Taller' },
  h5pactivity: { icon: 'bi-puzzle',          label: 'Actividad H5P' },
}
function modIcon(modname: string): string {
  return MOD_META[modname]?.icon ?? 'bi-app'
}
function modLabel(modname: string): string {
  return MOD_META[modname]?.label ?? modname
}

function bulkAllSections(state: boolean) {
  for (const s of structure.value) {
    s.included = state
    for (const a of s.activities) a.included = state
  }
}
function bulkResetNames() {
  for (const s of structure.value) {
    s.name = s.origName
    for (const a of s.activities) a.name = a.origName
  }
}

const disabledSectionsCount = computed(() => structure.value.filter(s => !s.included).length)
const renamedSectionsCount = computed(() => structure.value.filter(s => s.name !== s.origName).length)
const disabledActivitiesCount = computed(() =>
  structure.value.reduce((sum, s) => sum + s.activities.filter(a => !a.included).length, 0),
)
const renamedActivitiesCount = computed(() =>
  structure.value.reduce((sum, s) => sum + s.activities.filter(a => a.name !== a.origName).length, 0),
)

const smgpMeta = reactive({
  duration_hours: 0,
  level: 'beginner',
  completion_percentage: 100,
  is_pill: 0,
  smartmind_code: '',
  sepe_code: '',
  description: '',
  course_category: 0,
})
const smgpObjectives = ref<string[]>([])

// MetadataFields emits a fresh object on every keystroke (spread of
// props.modelValue). Binding that with v-model to a `reactive` const
// silently breaks reactivity — the assignment doesn't replace the
// proxy and the render context desyncs, which visibly resets the
// other fields when typing into description/objectives. Mutate in
// place instead so the proxy keeps its identity.
function onSmgpMetaUpdate(next: Partial<typeof smgpMeta>) {
  Object.assign(smgpMeta, next)
}

// ── Step 5 review helpers ─────────────────────────────────────
const reviewLevelLabel = computed(() => {
  const l = smgpMeta.level
  if (!l) return '—'
  const key = `editor.level_${l}`
  return te(key) ? t(key) : l
})
const reviewCategoryName = computed(() => {
  const cat = smgpCatalogueCategories.value.find(c => c.id === smgpMeta.course_category)
  return cat ? cat.name : '—'
})

// ────────────────────────────────────────────────────────────
// Step 6 — execute
// ────────────────────────────────────────────────────────────
const executing = ref(false)
const executeError = ref<string | null>(null)
const executeResult = ref<any>(null)
const processingPhase = ref('')
const elapsedSeconds = ref(0)
let elapsedTimer: ReturnType<typeof setInterval> | null = null

async function executeRestore() {
  step.value = 6
  executing.value = true
  executeError.value = null
  processingPhase.value = 'preparing'
  elapsedSeconds.value = 0
  if (elapsedTimer) clearInterval(elapsedTimer)
  elapsedTimer = setInterval(() => { elapsedSeconds.value++ }, 1000)

  // Build the structure JSON in the shape the observer expects
  // (sectionKey/name/origName + activities[{actKey,name,origName}]).
  const structureJson = structure.value.map(s => ({
    sectionKey: s.sectionKey,
    section_id: s.section_id,
    section_number: s.section_number,
    name: s.name,
    origName: s.origName,
    included: s.included,
    userinfo: s.userinfo,
    activities: s.activities.map(a => ({
      actKey: a.actKey,
      cmid: a.cmid,
      modname: a.modname,
      name: a.name,
      origName: a.origName,
      included: a.included,
      userinfo: a.userinfo,
      url: a.url ?? '',
      intro: a.intro ?? '',
      draftitemid: a.draftitemid ?? 0,
      filename: a.filename ?? '',
      deferred: !!a.deferred,
    })),
    /** SharePoint extras dropped by the admin onto this section; the
     *  backend re-hydrates each entry from $SESSION->smgp_sp_manifest
     *  to find the actual downloadable item id. */
    spExtras: s.spExtras.map(x => ({
      type: x.type,
      name: x.name,
      size: x.size,
    })),
  }))

  processingPhase.value = 'restoring'

  const result = await call<{
    success: boolean
    error?: string
    courseid?: number
    course_url?: string
    companies_assigned?: number
    sections_restored?: number
    activities_restored?: number
    sp_extras_applied?: number
    deferred_failures?: Array<{ modname: string; name: string; error: string }>
  }>('local_sm_graphics_plugin_restore_execute', {
    backupid: prepareResult.value.backupid,
    categoryid: destination.categoryid,
    fullname: destination.fullname,
    shortname: destination.shortname,
    startdate: destination.startdate ? Math.floor(new Date(destination.startdate).getTime() / 1000) : 0,
    companyids: selectedCompanyIds.value.join(','),
    settings_json: JSON.stringify(customSettings),
    course_structure_json: JSON.stringify(structureJson),
    smgp_fields_json: JSON.stringify({
      smgp_duration_hours: smgpMeta.duration_hours,
      smgp_level: smgpMeta.level,
      smgp_completion_percentage: smgpMeta.completion_percentage,
      smgp_smartmind_code: smgpMeta.smartmind_code,
      smgp_sepe_code: smgpMeta.sepe_code,
      smgp_description: smgpMeta.description,
      smgp_catalogue_cat: smgpMeta.course_category,
      smgp_objectives_data: JSON.stringify(smgpObjectives.value),
    }),
  })
  if (elapsedTimer) clearInterval(elapsedTimer)
  executing.value = false
  if (result.error || !result.data?.success) {
    executeError.value = result.error || result.data?.error || 'Restore failed.'
  } else {
    executeResult.value = result.data
    step.value = 7
  }
}

// ────────────────────────────────────────────────────────────
// Step 7 — restart
// ────────────────────────────────────────────────────────────
function resetWizard() {
  step.value = 0
  landingFile.value = null
  if (landingFileInput.value) landingFileInput.value.value = ''
  sharepointScan.value = null
  prepareResult.value = null
  destination.fullname = ''
  destination.shortname = ''
  destination.target = 'new'
  destination.targetCourseId = 0
  settingsList.value = []
  for (const k of Object.keys(customSettings)) delete customSettings[k]
  structure.value = []
  smgpMeta.duration_hours = 0
  smgpMeta.level = 'beginner'
  smgpMeta.completion_percentage = 100
  smgpMeta.smartmind_code = ''
  smgpMeta.sepe_code = ''
  smgpMeta.description = ''
  smgpMeta.course_category = 0
  smgpObjectives.value = []
  executeResult.value = null
  executeError.value = null
}

// ────────────────────────────────────────────────────────────
// SharePoint courseloader handoff
// ────────────────────────────────────────────────────────────
function consumeSharepointHandoff() {
  if (typeof window === 'undefined') return
  const filename = window.sessionStorage.getItem('smgp_restore_sp_filename')
  if (!filename) return
  // Clear immediately so a back-button bounce doesn't re-trigger prepare.
  window.sessionStorage.removeItem('smgp_restore_sp_filename')

  // Pull the courseloader's scan manifest so step 1 can render the
  // SCORM/PDF/Evaluation file table inside its Confirm card.
  const scanRaw = window.sessionStorage.getItem('smgp_restore_sp_scan')
  if (scanRaw) {
    try { sharepointScan.value = JSON.parse(scanRaw) } catch { /* ignore */ }
    window.sessionStorage.removeItem('smgp_restore_sp_scan')
  }

  uploading.value = true
  call<any>('local_sm_graphics_plugin_restore_prepare', {
    filename,
    draftitemid: 0,
  }).then((result) => {
    uploading.value = false
    if (result.error || !result.data?.success) {
      executeError.value = result.error || result.data?.error || 'Failed to load SharePoint backup.'
      return
    }
    prepareResult.value = result.data
    destination.fullname = result.data.original_fullname || ''
    destination.shortname = (result.data.original_shortname || '') + '_' + Date.now()
    // Skip the landing page entirely on SharePoint handoff — go straight
    // to step 1 so the admin sees the prepared backup info immediately.
    step.value = 1
    fetchSchema()
  })
}
consumeSharepointHandoff()
</script>

<style scoped lang="scss">
.smgp-restore {
  max-width: 1100px;
  margin: 2rem auto;
  padding: 0 1rem;

  // ════════════════════════════════════════════════════════════
  // Landing page (step 0)
  // ════════════════════════════════════════════════════════════
  &__landing {
    padding: 0;
  }
  &__landing-header {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    margin-bottom: 1.5rem;
  }
  &__landing-icon {
    font-size: 1.75rem;
    color: #10b981;
    margin-top: 0.15rem;
  }
  &__landing-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.25rem;
  }
  &__landing-desc {
    color: #64748b;
    margin: 0;
  }

  &__landing-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    overflow: hidden;
  }
  &__landing-card-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: #f8fafc;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    color: #1e293b;

    i { color: #10b981; font-size: 1.25rem; }
    h2 {
      font-size: 1rem;
      font-weight: 700;
      margin: 0;
    }
  }
  &__landing-card-body {
    padding: 1.5rem;
  }
  &__landing-pick {
    background: #10b981;
    border-color: #10b981;
    color: #fff;
    border-radius: 8px;
    padding: 0.55rem 1.1rem;
    font-weight: 600;
    margin-bottom: 1rem;
    &:hover, &:focus, &:active { background: #15803d; border-color: #15803d; color: #fff; }
  }
  &__dropzone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2.5rem 1.5rem;
    border: 2px dashed #6ee7b7;
    border-radius: 10px;
    background: #f0fdf4;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;
    &.is-dragover { background: #dcfce7; border-color: #10b981; }
    &.has-file    { border-color: #10b981; }
  }
  &__dropzone-input {
    position: absolute;
    width: 1px; height: 1px;
    opacity: 0;
    pointer-events: none;
  }
  &__dropzone-icon {
    font-size: 3rem;
    color: #10b981;
    line-height: 1;
  }
  &__dropzone-text { color: #047857; font-size: 0.95rem; text-align: center; }

  &__landing-actions {
    display: flex;
    justify-content: center;
    margin-top: 1.5rem;
  }
  &__landing-restore {
    background: #10b981;
    border-color: #10b981;
    color: #fff;
    border-radius: 8px;
    padding: 0.55rem 1.6rem;
    font-weight: 600;
    &:hover, &:focus, &:active { background: #15803d; border-color: #15803d; color: #fff; }
    &:disabled { opacity: 0.6; }
  }
  &__landing-required {
    margin-top: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: #dc2626;
    font-size: 0.9rem;
    i { font-size: 1rem; }
  }

  // ────────────────────────────────────────────────────────────
  // Landing zone tables (course / user / in-progress)
  // ────────────────────────────────────────────────────────────
  &__landing-zone {
    margin-top: 2rem;
  }
  &__landing-zone-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;

    i { color: #10b981; font-size: 1.1rem; }
    h3 {
      font-size: 1.05rem;
      font-weight: 700;
      color: #1e293b;
      margin: 0;
    }
  }
  &__landing-zone-desc {
    margin: 0 0 0.5rem;
    color: #64748b;
    font-size: 0.9rem;
  }
  &__zone-table {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);

    // Beat Bootstrap's .table CSS variables so the wrapper white shows
    // through every row, not just the headers.
    table {
      --bs-table-bg: #fff;
      --bs-table-striped-bg: #fff;
      --bs-table-hover-bg: #f0fdf4;
      --bs-table-color: #1e293b;
      margin: 0;
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: #fff;
    }
    thead th {
      background: #fff !important;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 0.7rem 0.9rem;
      border-bottom: 1px solid #e2e8f0;
    }
    tbody tr { background: #fff; }
    tbody td {
      padding: 0.65rem 0.9rem;
      border-top: 1px solid #f1f5f9;
      vertical-align: middle;
      color: #1e293b;
      font-size: 0.88rem;
      background: #fff;
    }
    tbody tr:first-child td { border-top: none; }
    tbody tr:hover td { background: #f0fdf4; }
    tbody td .btn { font-size: 0.78rem; }

    .btn-success {
      background: #10b981;
      border-color: #10b981;
      color: #fff;
      &:hover { background: #15803d; border-color: #15803d; }
    }
    .btn-outline-success {
      color: #10b981;
      border-color: #6ee7b7;
      &:hover { background: #f0fdf4; color: #15803d; border-color: #10b981; }
    }
  }

  // ════════════════════════════════════════════════════════════
  // Step 1 wrapper — completely transparent, no chrome.
  // The cards stand on their own.
  // ════════════════════════════════════════════════════════════
  &__step1 {
    background: transparent;
    border: none;
    padding: 0;
  }

  // ════════════════════════════════════════════════════════════
  // Confirm step cards
  // ════════════════════════════════════════════════════════════
  &__confirm-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);

    h3 {
      font-size: 1rem;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 0.85rem;
    }

    .form-control,
    .form-select {
      background-color: #fff;
    }
  }
  &__confirm-grid {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 0.5rem 1rem;
    margin: 0;
    border-top: 1px solid #f1f5f9;
    dt {
      color: #64748b;
      font-weight: 500;
      padding: 0.5rem 0;
      border-bottom: 1px solid #f1f5f9;
    }
    dd {
      margin: 0;
      color: #1e293b;
      padding: 0.5rem 0;
      border-bottom: 1px solid #f1f5f9;
    }
    dt:last-of-type, dd:last-of-type { border-bottom: none; }
  }
  &__confirm-subtext {
    color: #94a3b8;
    font-size: 0.78rem;
    margin-top: 0.15rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  }

  &__confirm-checklist {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    tr { border-bottom: 1px solid #f1f5f9; }
    tr:last-child { border-bottom: none; }
  }
  &__confirm-checklist-label {
    color: #64748b;
    font-weight: 500;
    padding: 0.55rem 0;
  }
  &__confirm-checklist-value {
    text-align: right;
    padding: 0.55rem 0;
    i { font-size: 1.1rem; }
  }
  &__confirm-subheading {
    margin-top: 1rem;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
  }
  &__confirm-sections {
    margin: 0;
    padding: 0;
    list-style: none;
    li {
      padding: 0.4rem 0;
      border-bottom: 1px solid #f1f5f9;
      color: #1e293b;
      &:last-child { border-bottom: none; }
    }
  }

  // ── Confirm-step section cards (rich, with activity tables) ─
  &__confirm-section-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
  }
  &__confirm-section {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
  }
  &__confirm-section-header {
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
    padding: 0.7rem 1rem;
    background: #f8fafc;
    border-left: 3px solid #10b981;
    color: #1e293b;
    strong { color: #1e293b; }
  }
  &__confirm-section-note {
    margin-left: auto;
    color: #94a3b8;
    font-style: italic;
    font-size: 0.85rem;
  }
  &__confirm-activities {
    padding: 0.85rem 1rem;
  }
  &__confirm-activities-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    font-weight: 600;
    margin-bottom: 0.4rem;
  }
  &__confirm-activities-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #f1f5f9;
    border-radius: 6px;
    overflow: hidden;

    thead th {
      background: #f8fafc;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 0.5rem 0.85rem;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
    }
    tbody td {
      padding: 0.55rem 0.85rem;
      border-top: 1px solid #f1f5f9;
      vertical-align: middle;
      color: #1e293b;
      font-size: 0.88rem;
    }
    tbody tr:first-child td { border-top: none; }
    tbody tr:hover td { background: #f0fdf4; }
  }
  &__confirm-mod-icon {
    color: #94a3b8;
    margin-right: 0.4rem;
    font-size: 0.95rem;
  }

  &__confirm-actions {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1.5rem;
  }
  &__continue {
    background: #10b981;
    border-color: #10b981;
    color: #fff;
    border-radius: 8px;
    padding: 0.55rem 1.5rem;
    font-weight: 600;
    &:hover, &:focus, &:active { background: #15803d; border-color: #15803d; color: #fff; }
  }

  // Outline back button — green border + text instead of grey.
  &__back-btn {
    background: #fff;
    border: 1px solid #10b981;
    color: #10b981;
    border-radius: 8px;
    padding: 0.55rem 1.1rem;
    font-weight: 500;
    transition: background-color 0.15s ease, color 0.15s ease;
    &:hover, &:focus, &:active {
      background: #f0fdf4;
      border-color: #15803d;
      color: #15803d;
    }
  }

  // ════════════════════════════════════════════════════════════
  // Step 2 — Destination cards
  // ════════════════════════════════════════════════════════════
  &__dest-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 0.25rem;
    border-bottom: 1px solid #f1f5f9;
    &:last-of-type { border-bottom: none; }
  }
  &__dest-row-label {
    color: #1e293b;
    font-weight: 500;
    flex: 1;
  }
  &__dest-radio {
    appearance: none;
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border: 2px solid #cbd5e1;
    border-radius: 50%;
    cursor: pointer;
    position: relative;
    flex-shrink: 0;
    margin: 0;
    transition: border-color 0.15s ease;
    &:hover { border-color: #10b981; }
    &:checked {
      border-color: #10b981;
      &::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #10b981;
      }
    }
  }
  &__dest-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin: 1rem 0 0.65rem;
    h4 {
      margin: 0;
      font-size: 0.9rem;
      font-weight: 600;
      color: #1e293b;
    }
  }
  &__dest-search {
    position: relative;
    width: 240px;
    max-width: 100%;
    input { padding-right: 2rem; background-color: #fff; }
  }
  &__dest-search-icon {
    position: absolute;
    right: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
    font-size: 0.85rem;
  }
  &__dest-table {
    tbody tr {
      cursor: pointer;
      &:hover { background: #f8fafc; }
      &.is-selected-row { background: #ecfdf5; }
    }
  }

  // ════════════════════════════════════════════════════════════
  // Step 4 — Structure editor (sections + activities + SP extras)
  // ════════════════════════════════════════════════════════════
  &__card-icon {
    color: #1e293b;
    margin-right: 0.4rem;
    &--purple { color: #1e293b; }
    &--blue   { color: #2563eb; }
  }
  &__struct-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
  }
  &__struct-section {
    border: 1px solid #e2e8f0;
    border-left: 3px solid #10b981;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
    transition: box-shadow 0.15s ease, border-color 0.15s ease;
    &.is-drop-target {
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
    }
    &.is-disabled { opacity: 0.55; }
  }
  &__struct-section-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 0.85rem;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
  }
  &__struct-handle {
    color: #cbd5e1;
    font-size: 0.95rem;
    cursor: grab;
    flex-shrink: 0;
  }
  &__struct-toggle {
    flex-shrink: 0;
    padding-left: 2.4em;
    min-height: auto;
    margin: 0;
    .form-check-input {
      width: 2.4em;
      height: 1.3em;
      margin-left: -2.4em;
      margin-top: 0;
      cursor: pointer;
      border-color: #cbd5e1;
      background-color: #e2e8f0;
      &:checked { background-color: #10b981; border-color: #10b981; }
    }
  }
  &__tip {
    color: #94a3b8;
    font-size: 0.7rem;
    cursor: help;
    margin-left: 0.15rem;
  }
  &__struct-course-fields {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    @media (max-width: 700px) { grid-template-columns: 1fr; }
    label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: #475569;
      margin-bottom: 0.25rem;
    }
    .form-control { background: #fff; }
  }
  // ── Step 5 review styles ────────────────────────────────────
  &__review-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem 1.25rem;
    margin: 0 0 1rem;
    @media (max-width: 700px) { grid-template-columns: 1fr; }
  }
  &__review-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }
  &__review-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.2rem;
    i { color: #94a3b8; font-size: 0.85rem; }
  }
  &__review-value {
    color: #1e293b;
    font-weight: 500;
    font-size: 0.95rem;
    word-break: break-word;
  }
  &__review-rich {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f1f5f9;
    h4 {
      font-size: 0.82rem;
      font-weight: 600;
      color: #64748b;
      margin: 0 0 0.4rem;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      i { color: #94a3b8; font-size: 0.9rem; }
    }
  }
  &__review-rich-body {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.65rem 0.85rem;
    color: #1e293b;
    font-size: 0.9rem;
    :deep(p) { margin: 0 0 0.4rem; &:last-child { margin-bottom: 0; } }
    :deep(ul), :deep(ol) { margin: 0 0 0.4rem 1.1rem; padding: 0; }
  }
  &__review-obj {
    margin: 0;
    padding-left: 1.1rem;
    color: #1e293b;
    font-size: 0.9rem;
    li { padding: 0.15rem 0; }
  }
  &__review-state {
    flex-shrink: 0;
    font-size: 1rem;
  }
  &__struct-name-static {
    flex: 1;
    color: #1e293b;
    font-size: 0.95rem;
    font-weight: 600;
  }
  &__struct-list--readonly {
    .smgp-restore__struct-activity {
      cursor: default;
      &.is-disabled { opacity: 0.55; }
    }
  }

  &__struct-add-act {
    align-self: flex-start;
    margin: 0.55rem 0.85rem 0.7rem 1.6rem;
    background: transparent;
    border: 1px dashed #10b981;
    border-radius: 6px;
    color: #10b981;
    font-size: 0.8rem;
    padding: 0.3rem 0.75rem;
    cursor: pointer;
    transition: background 0.15s ease;
    &:hover { background: #f0fdf4; }
  }
  &__struct-add-sec {
    align-self: stretch;
    margin-top: 0.5rem;
    background: #fff;
    border: 1px dashed #10b981;
    border-radius: 8px;
    color: #10b981;
    font-weight: 600;
    padding: 0.65rem;
    cursor: pointer;
    transition: background 0.15s ease;
    &:hover { background: #f0fdf4; }
  }
  &__struct-name-input {
    flex: 1;
    border: none;
    background: transparent;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.95rem;
    padding: 0.25rem 0.4rem;
    border-radius: 4px;
    &:focus { outline: none; background: #fff; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25); }
  }
  &__struct-count {
    flex-shrink: 0;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.72rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
  }
  &__struct-activities {
    display: flex;
    flex-direction: column;
  }
  &__struct-activity {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 0.85rem 0.55rem 1.6rem;
    border-bottom: 1px solid #f1f5f9;
    &:last-child { border-bottom: none; }
    &--sp { background: #f8fafc; }
  }
  &__struct-icon-wrap {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
  }
  &__struct-activity-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    strong {
      color: #1e293b;
      font-size: 0.88rem;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  }
  &__struct-act-name {
    border: none;
    background: transparent;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.88rem;
    padding: 0.15rem 0.3rem;
    border-radius: 4px;
    &:focus { outline: none; background: #fff; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25); }
  }
  &__struct-modtype {
    color: #94a3b8;
    font-size: 0.75rem;
    padding-left: 0.3rem;
  }
  &__deferred-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin-left: 0.4rem;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: #fef3c7;
    color: #b45309;
    font-size: 0.7rem;
    font-weight: 600;
    i { font-size: 0.7rem; }
  }
  &__struct-remove {
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 0.25rem 0.4rem;
    &:hover { color: #ef4444; }
  }

  // ── SharePoint extras palette ──
  &__sp-palette {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }
  &__sp-chip {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.85rem;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    background: #fff;
    cursor: grab;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    &:hover {
      border-color: #10b981;
      box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
    }
    &:active { cursor: grabbing; }
  }
  &__sp-chip-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    strong {
      color: #1e293b;
      font-size: 0.85rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    span { color: #94a3b8; font-size: 0.72rem; }
  }

  // ════════════════════════════════════════════════════════════
  // Step 3 — Settings grid (numbered, two columns, check/X toggles)
  // ════════════════════════════════════════════════════════════
  &__settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.25rem 1.5rem;
    @media (max-width: 768px) {
      grid-template-columns: 1fr;
    }
  }
  &__settings-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.25rem;
    border-bottom: 1px solid #f1f5f9;
    min-height: 44px;
  }
  &__settings-num {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #ecfdf5;
    color: #10b981;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  &__settings-label {
    flex: 1;
    margin: 0;
    color: #475569;
    font-size: 0.88rem;
    font-weight: 500;
  }
  &__settings-toggle {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    padding: 0;
    border: none;
    background: transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    line-height: 1;
    cursor: pointer;
    color: #cbd5e1;
    transition: color 0.15s ease, transform 0.1s ease;
    &.is-on { color: #10b981; }
    &:not(.is-on) { color: #f87171; }
    &:hover:not(:disabled) { transform: scale(1.15); }
    &.is-locked,
    &:disabled { opacity: 0.5; cursor: not-allowed; }
  }
  &__settings-select,
  &__settings-text {
    flex-shrink: 0;
    width: 90px;
  }
  &__settings-text { width: 140px; }

  // ════════════════════════════════════════════════════════════
  // SharePoint extras file table inside Confirm
  // ════════════════════════════════════════════════════════════
  &__sp-table {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    table {
      margin: 0;
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    thead th {
      background: #f8fafc;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 0.6rem 0.9rem;
      border-bottom: 1px solid #e2e8f0;
    }
    tbody td {
      padding: 0.55rem 0.9rem;
      border-top: 1px solid #f1f5f9;
      vertical-align: middle;
      color: #1e293b;
      font-size: 0.9rem;
    }
    tbody tr:hover td { background: #f0fdf4; }
  }
  &__sp-icon {
    text-align: center;
    font-size: 1.25rem;
    line-height: 1;
  }
  &__sp-badge {
    display: inline-block;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
  }

  &__title { font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
  &__steps {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem;
    border-bottom: 2px solid #e2e8f0;
    li {
      padding: 0.5rem 0.75rem;
      color: #94a3b8;
      border-bottom: 3px solid transparent;
      &.is-current { color: #10b981; border-color: #10b981; font-weight: 600; }
      &.is-done { color: #64748b; }
    }
  }
  &__step-num { font-weight: 700; margin-right: 0.25rem; }

  &__section {
    position: relative;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #10b981;
    border-radius: 12px;
    padding: 1.5rem 1.5rem 1.5rem 1.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    margin-bottom: 1.25rem;
  }
  &__section-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.5rem;
  }
  &__field {
    margin-bottom: 1rem;
    label { display: block; font-weight: 600; margin-bottom: 0.35rem; color: #1e293b; }
  }
  &__radio-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
  }
  &__radio {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 500;
    cursor: pointer;
    input[type="radio"] { accent-color: #10b981; }
  }

  &__details, &__included {
    margin-top: 1rem;
    h3 {
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #94a3b8;
      margin-bottom: 0.5rem;
    }
  }
  &__details dl {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 0.35rem 1rem;
    margin: 0;
    dt { font-weight: 600; color: #475569; }
    dd { margin: 0; color: #1e293b; }
  }
  &__badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  &__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 500;
    border: 1px solid;
    &--on  { background: rgba(16, 185, 129, 0.1); color: #047857; border-color: #6ee7b7; }
    &--off { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; }
  }

  &__course-list {
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    max-height: 240px;
    overflow-y: auto;
  }
  &__course-row {
    display: flex;
    flex-direction: column;
    padding: 0.55rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    &:last-child { border-bottom: none; }
    &:hover, &.is-selected { background: #f0fdf4; }
    strong { color: #1e293b; }
  }

  &__company-search {
    margin-bottom: 0.85rem;
    max-width: 320px;

    .form-control {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0.55rem 0.85rem;
      font-size: 0.9rem;
      &:focus { background: #fff; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15); border-color: #10b981; }
    }
  }
  &__company-table {
    max-height: 360px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);

    table {
      margin-bottom: 0;
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }
    thead th {
      position: sticky;
      top: 0;
      background: #f8fafc;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 0.85rem 1rem;
      border-bottom: 1px solid #e2e8f0;
      border-right: 1px solid #eef2f7;
      vertical-align: middle;
      text-align: left;
      &:last-child { border-right: none; }
    }
    tbody td {
      padding: 0.85rem 1rem;
      border-top: 1px solid #eef2f7;
      border-right: 1px solid #eef2f7;
      vertical-align: middle;
      background: #fff;
      &:last-child { border-right: none; }
    }
    tbody tr:first-child td { border-top: none; }
    tbody tr:hover td { background: #f0fdf4; }
    tbody td strong { color: #1e293b; font-weight: 600; }
    tbody td.text-muted { color: #94a3b8 !important; font-size: 0.9rem; }

    th.smgp-restore__toggle-cell,
    td.smgp-restore__toggle-cell {
      position: relative;
      width: 60px;
      min-width: 60px;
      height: 48px;
      padding: 0 !important;
      vertical-align: middle;
    }
  }

  &__toggle-wrap {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: block;
    padding: 0 !important;
    margin: 0 !important;
    min-height: 0;
    line-height: 0;
    &.form-switch { padding-left: 0 !important; }

    .form-check-input {
      display: block;
      float: none !important;
      margin: 0 !important;
      width: 2.4rem;
      height: 1.3rem;
      cursor: pointer;
      border-color: #cbd5e1;
      background-color: #e2e8f0;
      &:checked {
        background-color: #10b981;
        border-color: #10b981;
      }
    }
  }

  // ── Step 3 settings rows ─────────────────────────────────────
  &__setting-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid #f1f5f9;
    &:last-child { border-bottom: none; }
  }
  &__setting-label { flex: 1; margin: 0; font-weight: 500; color: #1e293b; }
  &__setting-toggle {
    position: relative;
    width: 2.4rem;
    height: 1.3rem;
    transform: none;
    top: auto;
    left: auto;
    display: inline-block;
  }

  // ── Step 4 schema editor ─────────────────────────────────────
  &__schema-bulk {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
  }
  &__schema-tree {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    margin-bottom: 1.5rem;
  }
  &__schema-section {
    border-bottom: 1px solid #f1f5f9;
    &:last-child { border-bottom: none; }
  }
  &__schema-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.9rem;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    &--activity {
      background: #fff;
      padding-left: 2.25rem;
      &:hover { background: #f0fdf4; }
    }
    &:last-child { border-bottom: none; }
  }
  &__schema-toggle {
    position: relative;
    width: 2.4rem;
    height: 1.3rem;
    transform: none;
    top: auto;
    left: auto;
    display: inline-block;
    flex-shrink: 0;
  }
  &__schema-modname {
    display: inline-block;
    min-width: 70px;
    padding: 0.18rem 0.5rem;
    border-radius: 999px;
    background: #e0f2fe;
    color: #0369a1;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    text-align: center;
  }
  &__schema-name {
    flex: 1;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    &:focus { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15); }
  }
  &__schema-userinfo {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: #64748b;
    margin: 0;
    flex-shrink: 0;
    input[type="checkbox"] { accent-color: #10b981; }
  }

  &__metadata, &__objectives {
    margin-top: 1.5rem;
    h3 {
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #94a3b8;
      margin-bottom: 0.75rem;
    }
  }

  &__actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1.25rem;

    .btn-success {
      background-color: #10b981;
      border-color: #10b981;
      color: #fff;
      i { color: #fff; }
      &:hover, &:focus, &:active { background-color: #15803d; border-color: #15803d; color: #fff; }
    }
  }

  // ── Step 5 review blocks ─────────────────────────────────────
  &__review-block {
    margin-bottom: 1.25rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    &:last-of-type { border-bottom: none; }
    h3 {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #10b981;
      margin: 0 0 0.5rem;
    }
  }
  &__review {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 0.35rem 1rem;
    margin: 0;
    dt { font-weight: 600; color: #475569; }
    dd { margin: 0; color: #1e293b; }
  }
  &__review-list {
    margin: 0;
    padding-left: 1.25rem;
    color: #1e293b;
  }

  // ── Step 6 progress ──────────────────────────────────────────
  &__processing { text-align: center; padding: 1.5rem 0; }
  &__progress {
    height: 8px;
    border-radius: 999px;
    background: #ecfdf5;
    overflow: hidden;
    margin-bottom: 1rem;
  }
  &__progress-bar {
    width: 30%;
    height: 100%;
    background: linear-gradient(90deg, #10b981, #34d399, #10b981);
    background-size: 200% 100%;
    animation: smgp-restore-progress 1.5s linear infinite;
  }
  &__processing-phase {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
    text-transform: capitalize;
  }
}

@keyframes smgp-restore-progress {
  0%   { background-position: 0% 50%; transform: translateX(-100%); }
  50%  { transform: translateX(50%); }
  100% { background-position: 100% 50%; transform: translateX(300%); }
}
</style>
