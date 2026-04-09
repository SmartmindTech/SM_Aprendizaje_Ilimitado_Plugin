<template>
  <div class="smgp-admin-settings">
    <header class="smgp-admin-settings__header d-flex align-items-start gap-3">
      <button
        type="button"
        class="btn smgp-back-btn mt-1"
        @click="$router.back()"
      >
        <i class="icon-arrow-left" />
      </button>
      <div class="flex-grow-1">
        <h1 class="smgp-admin-settings__title">{{ $t('adminSettings.title') }}</h1>
        <p class="smgp-admin-settings__desc">{{ $t('adminSettings.desc') }}</p>
      </div>
    </header>

    <!-- Section badges (tabs) -->
    <div class="smgp-admin-settings__tabs" role="tablist">
      <button
        v-for="(group, idx) in groups"
        :key="group.key"
        type="button"
        role="tab"
        class="smgp-admin-settings__tab"
        :class="{ 'smgp-admin-settings__tab--active': activeTab === idx }"
        :aria-selected="activeTab === idx"
        @click="activeTab = idx"
      >
        <i :class="group.icon" class="smgp-admin-settings__tab-icon" />
        <span>{{ group.title }}</span>
      </button>
    </div>

    <!-- Active section's cards -->
    <div class="smgp-admin-settings__grid">
      <component
        :is="card.external ? 'a' : (NuxtLink as any)"
        v-for="card in groups[activeTab].cards"
        :key="card.to + card.label"
        v-bind="card.external ? { href: card.to } : { to: card.to }"
        class="smgp-admin-settings__card"
      >
        <span class="smgp-admin-settings__card-icon">
          <i :class="card.icon" />
        </span>
        <span class="smgp-admin-settings__card-text">
          <span class="smgp-admin-settings__card-title">{{ card.label }}</span>
          <span class="smgp-admin-settings__card-desc">{{ card.desc }}</span>
        </span>
      </component>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { NuxtLink } from '#components'

definePageMeta({ middleware: 'auth' })

const { t } = useI18n()
const authStore = useAuthStore()

interface Card {
  to: string
  icon: string
  label: string
  desc: string
  external?: boolean
}
interface Group {
  key: string
  title: string
  icon: string
  cards: Card[]
}

const activeTab = ref(0)

// Helper for Moodle admin URLs (rendered as <a>, opened in same tab).
const moodle = (path: string): string => `${authStore.wwwroot}${path}`

const groups = computed<Group[]>(() => [
  {
    key: 'platform',
    icon: 'icon-layers',
    title: t('adminSettings.groupPlatform'),
    cards: [
      { to: '/admin/iomad-dashboard', icon: 'icon-layers',         label: t('adminSettings.iomadLabel'),      desc: t('adminSettings.iomadDesc') },
      { to: '/management/users',      icon: 'icon-users',          label: t('adminSettings.usersLabel'),      desc: t('adminSettings.usersDesc') },
      { to: '/management/courses',    icon: 'icon-graduation-cap', label: t('adminSettings.coursesLabel'),    desc: t('adminSettings.coursesDesc') },
      { to: '/management/categories', icon: 'icon-folder',         label: t('adminSettings.categoriesLabel'), desc: t('adminSettings.categoriesDesc') },
      { to: '/admin/company-limits',  icon: 'icon-gauge',          label: t('adminSettings.limitsLabel'),     desc: t('adminSettings.limitsDesc') },
      { to: '/statistics',            icon: 'icon-bar-chart',      label: t('adminSettings.statsLabel'),      desc: t('adminSettings.statsDesc') },
      { to: '/admin/updates',         icon: 'icon-cloud-upload',   label: t('adminSettings.updatesLabel'),    desc: t('adminSettings.updatesDesc') },
      { to: '/admin/restore',         icon: 'icon-rotate-ccw',     label: t('adminSettings.restoreLabel'),    desc: t('adminSettings.restoreDesc') },
    ],
  },
  {
    key: 'iomad',
    icon: 'icon-building-2',
    title: t('adminSettings.groupIomad'),
    cards: [
      { to: moodle('/blocks/iomad_company_admin/index.php'),                      icon: 'icon-layout-grid',  label: t('adminSettings.iomadIndexLabel'),       desc: t('adminSettings.iomadIndexDesc'),       external: true },
      { to: moodle('/blocks/iomad_company_admin/company_list.php'),               icon: 'icon-building-2',   label: t('adminSettings.companiesLabel'),        desc: t('adminSettings.companiesDesc'),        external: true },
      { to: moodle('/blocks/iomad_company_admin/company_edit_form.php'),          icon: 'icon-circle-plus',  label: t('adminSettings.companyNewLabel'),       desc: t('adminSettings.companyNewDesc'),       external: true },
      { to: moodle('/blocks/iomad_company_admin/company_department.php'),         icon: 'icon-list-checks',  label: t('adminSettings.departmentsLabel'),      desc: t('adminSettings.departmentsDesc'),      external: true },
      { to: moodle('/blocks/iomad_company_admin/company_users_form.php'),         icon: 'icon-user-plus',    label: t('adminSettings.companyUsersLabel'),     desc: t('adminSettings.companyUsersDesc'),     external: true },
      { to: moodle('/blocks/iomad_company_admin/company_courses_form.php'),       icon: 'icon-book-open',    label: t('adminSettings.companyCoursesLabel'),   desc: t('adminSettings.companyCoursesDesc'),   external: true },
      { to: moodle('/blocks/iomad_company_admin/company_license_users_form.php'), icon: 'icon-credit-card',  label: t('adminSettings.companyLicensesLabel'),  desc: t('adminSettings.companyLicensesDesc'),  external: true },
      { to: moodle('/blocks/iomad_company_admin/company_capabilities.php'),       icon: 'icon-toggle-right', label: t('adminSettings.companyCapsLabel'),      desc: t('adminSettings.companyCapsDesc'),      external: true },
      { to: moodle('/blocks/iomad_company_admin/user_template.php'),              icon: 'icon-clipboard',    label: t('adminSettings.userTemplatesLabel'),    desc: t('adminSettings.userTemplatesDesc'),    external: true },
      { to: moodle('/blocks/iomad_company_admin/restrict_capabilities.php'),      icon: 'icon-link',         label: t('adminSettings.restrictCapsLabel'),     desc: t('adminSettings.restrictCapsDesc'),     external: true },
    ],
  },
  {
    key: 'appearance',
    icon: 'icon-image',
    title: t('adminSettings.groupAppearance'),
    cards: [
      { to: moodle('/admin/settings.php?section=local_sm_graphics_plugin'), icon: 'icon-settings',     label: t('adminSettings.pluginSettingsLabel'), desc: t('adminSettings.pluginSettingsDesc'), external: true },
      { to: moodle('/admin/settings.php?section=themesettingsmartmind'),    icon: 'icon-image',        label: t('adminSettings.themeLabel'),          desc: t('adminSettings.themeDesc'),          external: true },
      { to: moodle('/admin/plugins.php'),                                   icon: 'icon-package',      label: t('adminSettings.pluginsLabel'),        desc: t('adminSettings.pluginsDesc'),        external: true },
      { to: moodle('/admin/filters.php'),                                   icon: 'icon-toggle-left',  label: t('adminSettings.filtersLabel'),        desc: t('adminSettings.filtersDesc'),        external: true },
      { to: moodle('/admin/blocks.php'),                                    icon: 'icon-table',        label: t('adminSettings.blocksLabel'),         desc: t('adminSettings.blocksDesc'),         external: true },
      { to: moodle('/admin/settings.php?section=frontpagesettings'),        icon: 'icon-home',         label: t('adminSettings.frontpageLabel'),      desc: t('adminSettings.frontpageDesc'),      external: true },
    ],
  },
  {
    key: 'comms',
    icon: 'icon-languages',
    title: t('adminSettings.groupComms'),
    cards: [
      { to: moodle('/admin/tool/customlang/index.php'),         icon: 'icon-languages',      label: t('adminSettings.langLabel'),          desc: t('adminSettings.langDesc'),          external: true },
      { to: moodle('/admin/category.php?category=email'),       icon: 'icon-mail',           label: t('adminSettings.emailLabel'),         desc: t('adminSettings.emailDesc'),         external: true },
      { to: moodle('/admin/message.php'),                       icon: 'icon-message-square', label: t('adminSettings.messagingLabel'),     desc: t('adminSettings.messagingDesc'),     external: true },
      { to: moodle('/message/notificationpreferences.php'),     icon: 'icon-bell',           label: t('adminSettings.notificationsLabel'), desc: t('adminSettings.notificationsDesc'), external: true },
      { to: moodle('/admin/tool/task/scheduledtasks.php'),      icon: 'icon-clock',          label: t('adminSettings.tasksLabel'),         desc: t('adminSettings.tasksDesc'),         external: true },
    ],
  },
  {
    key: 'users',
    icon: 'icon-users',
    title: t('adminSettings.groupUsers'),
    cards: [
      { to: moodle('/admin/user.php'),                       icon: 'icon-user',         label: t('adminSettings.userListLabel'),    desc: t('adminSettings.userListDesc'),    external: true },
      { to: moodle('/user/editadvanced.php?id=-1'),          icon: 'icon-square-pen',   label: t('adminSettings.userNewLabel'),     desc: t('adminSettings.userNewDesc'),     external: true },
      { to: moodle('/admin/uploaduser.php'),                 icon: 'icon-upload',       label: t('adminSettings.userUploadLabel'),  desc: t('adminSettings.userUploadDesc'),  external: true },
      { to: moodle('/cohort/index.php'),                     icon: 'icon-tag',          label: t('adminSettings.cohortsLabel'),     desc: t('adminSettings.cohortsDesc'),     external: true },
      { to: moodle('/admin/category.php?category=authsettings'), icon: 'icon-lock',     label: t('adminSettings.authLabel'),        desc: t('adminSettings.authDesc'),        external: true },
      { to: moodle('/admin/roles/manage.php'),               icon: 'icon-badge-check',  label: t('adminSettings.rolesLabel'),       desc: t('adminSettings.rolesDesc'),       external: true },
      { to: moodle('/admin/roles/check.php?contextid=1'),    icon: 'icon-eye',          label: t('adminSettings.permissionsLabel'), desc: t('adminSettings.permissionsDesc'), external: true },
    ],
  },
  {
    key: 'courses',
    icon: 'icon-graduation-cap',
    title: t('adminSettings.groupCourses'),
    cards: [
      { to: moodle('/course/management.php'),                   icon: 'icon-folder-open',     label: t('adminSettings.coursesMgmtLabel'),  desc: t('adminSettings.coursesMgmtDesc'),  external: true },
      { to: moodle('/course/edit.php?category=1'),              icon: 'icon-pencil',          label: t('adminSettings.courseNewLabel'),    desc: t('adminSettings.courseNewDesc'),    external: true },
      { to: moodle('/admin/tool/uploadcourse/index.php'),       icon: 'icon-file-up',         label: t('adminSettings.courseUploadLabel'), desc: t('adminSettings.courseUploadDesc'), external: true },
      { to: moodle('/question/edit.php'),                       icon: 'icon-clipboard-check', label: t('adminSettings.qbankLabel'),        desc: t('adminSettings.qbankDesc'),        external: true },
      { to: moodle('/grade/edit/scale/index.php'),              icon: 'icon-trophy',          label: t('adminSettings.gradesLabel'),       desc: t('adminSettings.gradesDesc'),       external: true },
      { to: moodle('/badges/index.php?type=1'),                 icon: 'icon-award',           label: t('adminSettings.badgesLabel'),       desc: t('adminSettings.badgesDesc'),       external: true },
      { to: moodle('/admin/tool/lp/learningplans.php'),         icon: 'icon-flag',            label: t('adminSettings.competenciesLabel'), desc: t('adminSettings.competenciesDesc'), external: true },
      { to: moodle('/admin/repository.php'),                    icon: 'icon-paperclip',       label: t('adminSettings.repositoriesLabel'), desc: t('adminSettings.repositoriesDesc'), external: true },
    ],
  },
  {
    key: 'backups',
    icon: 'icon-database',
    title: t('adminSettings.groupBackups'),
    cards: [
      { to: moodle('/admin/category.php?category=backups'), icon: 'icon-database',  label: t('adminSettings.backupsLabel'),    desc: t('adminSettings.backupsDesc'),    external: true },
      { to: moodle('/backup/restorefile.php?contextid=1'),  icon: 'icon-rotate-cw', label: t('adminSettings.moodleRestoreLabel'), desc: t('adminSettings.moodleRestoreDesc'), external: true },
      { to: moodle('/report/backups/index.php'),            icon: 'icon-newspaper', label: t('adminSettings.backupLogsLabel'), desc: t('adminSettings.backupLogsDesc'), external: true },
    ],
  },
  {
    key: 'system',
    icon: 'icon-cpu',
    title: t('adminSettings.groupSystem'),
    cards: [
      { to: moodle('/admin/category.php?category=server'),         icon: 'icon-cpu',         label: t('adminSettings.serverLabel'),      desc: t('adminSettings.serverDesc'),      external: true },
      { to: moodle('/admin/settings.php?section=performance'),     icon: 'icon-zap',         label: t('adminSettings.perfLabel'),        desc: t('adminSettings.perfDesc'),        external: true },
      { to: moodle('/admin/settings.php?section=maintenancemode'), icon: 'icon-wrench',      label: t('adminSettings.maintenanceLabel'), desc: t('adminSettings.maintenanceDesc'), external: true },
      { to: moodle('/admin/purgecaches.php'),                      icon: 'icon-refresh-cw',  label: t('adminSettings.cachesLabel'),      desc: t('adminSettings.cachesDesc'),      external: true },
      { to: moodle('/admin/phpinfo.php'),                          icon: 'icon-info',        label: t('adminSettings.phpinfoLabel'),     desc: t('adminSettings.phpinfoDesc'),     external: true },
      { to: moodle('/report/log/index.php'),                       icon: 'icon-list',        label: t('adminSettings.logsLabel'),        desc: t('adminSettings.logsDesc'),        external: true },
      { to: moodle('/admin/webservice/service.php'),               icon: 'icon-globe',       label: t('adminSettings.webservicesLabel'), desc: t('adminSettings.webservicesDesc'), external: true },
      { to: moodle('/admin/tool/customlang/edit.php'),             icon: 'icon-file-code',   label: t('adminSettings.envLabel'),         desc: t('adminSettings.envDesc'),         external: true },
    ],
  },
  {
    key: 'security',
    icon: 'icon-shield-check',
    title: t('adminSettings.groupSecurity'),
    cards: [
      { to: moodle('/admin/category.php?category=security'),       icon: 'icon-shield-check', label: t('adminSettings.securityLabel'),  desc: t('adminSettings.securityDesc'),  external: true },
      { to: moodle('/admin/tool/dataprivacy/dataregistry.php'),    icon: 'icon-eye-off',      label: t('adminSettings.privacyLabel'),   desc: t('adminSettings.privacyDesc'),   external: true },
      { to: moodle('/admin/category.php?category=reports'),        icon: 'icon-bar-chart-2',  label: t('adminSettings.reportsLabel'),   desc: t('adminSettings.reportsDesc'),   external: true },
      { to: moodle('/admin/tool/monitor/index.php'),               icon: 'icon-rss',          label: t('adminSettings.eventsLabel'),    desc: t('adminSettings.eventsDesc'),    external: true },
      { to: moodle('/admin/search.php'),                           icon: 'icon-search',       label: t('adminSettings.searchLabel'),    desc: t('adminSettings.searchDesc'),    external: true },
    ],
  },
])
</script>

<style lang="scss" scoped>
.smgp-admin-settings {
  padding: 1.5rem 2rem 3rem;
  max-width: 1400px;
  margin: 0 auto;

  &__header {
    margin-bottom: 1.5rem;
  }

  &__title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 0.25rem;
  }

  &__desc {
    color: #6b7280;
    margin: 0;
    font-size: 0.9rem;
  }

  // ── Section badges (tabs) ──
  &__tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #e5e7eb;
  }

  &__tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.1rem;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    color: #374151;
    font-size: 0.88rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;

    &:hover {
      background: #f3f4f6;
      border-color: #d1d5db;
    }

    &--active {
      background: #10b981;
      color: #fff;
      border-color: #10b981;

      &:hover {
        background: #059669;
        border-color: #059669;
      }
    }
  }

  &__tab-icon {
    font-size: 1rem;

    .smgp-admin-settings__tab--active &::before {
      color: #fff !important;
    }
  }

  // ── Cards grid (only the active section's cards) ──
  // Bigger cards than the previous compact list — vertical stack with title
  // and description on their own lines, tighter columns to 4-up at 1280px+.
  &__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
  }

  &__card {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    padding: 1.1rem 1.25rem;
    background: #fff;
    border: 1px solid #f3f4f6;
    border-radius: 14px;
    text-decoration: none;
    color: inherit;
    min-height: 96px;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;

    &:hover {
      transform: translateY(-2px);
      border-color: #d1fae5;
      box-shadow: 0 8px 20px rgba(16, 185, 129, 0.1);
      text-decoration: none;
      color: inherit;
    }
  }

  &__card-icon {
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 12px;

    i {
      font-size: 1.35rem;
      color: #10b981;
    }
  }

  &__card-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
    flex: 1;
  }

  &__card-title {
    font-size: 0.98rem;
    font-weight: 600;
    color: #1a1a1a;
    line-height: 1.3;
  }

  &__card-desc {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.4;
  }
}
</style>
