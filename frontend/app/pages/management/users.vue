<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="smgp-mgmt-page">
    <header class="smgp-mgmt-page__header">
      <h1 class="smgp-mgmt-page__title">{{ $t('management.users.title') }}</h1>
      <p class="smgp-mgmt-page__desc">
        {{ $t('management.users.desc') }}
        <template v-if="data.companyname"> · <strong>{{ data.companyname }}</strong></template>
      </p>
    </header>

    <!-- Quick actions card grid -->
    <h2 class="smgp-mgmt-page__section-title">
      <i class="icon-zap" aria-hidden="true" />
      {{ $t('management.users.section_actions') }}
    </h2>
    <div class="smgp-mgmt-grid">
      <NuxtLink to="/management/upload" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-upload" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.users.card_upload') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.users.card_upload_desc') }}</span>
        </span>
      </NuxtLink>
      <a :href="`${authStore.wwwroot}/blocks/iomad_company_admin/editusers.php`" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-user-plus" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.users.card_create') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.users.card_create_desc') }}</span>
        </span>
      </a>
      <a :href="`${authStore.wwwroot}/blocks/iomad_company_admin/company_edit_form.php`" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-pencil" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.othermgmt.card_companyDetails') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.othermgmt.card_companyDetails_desc') }}</span>
        </span>
      </a>
      <a :href="`${authStore.wwwroot}/blocks/iomad_company_admin/company_department.php`" class="smgp-mgmt-card">
        <span class="smgp-mgmt-card__icon"><i class="icon-list-tree" /></span>
        <span class="smgp-mgmt-card__text">
          <span class="smgp-mgmt-card__title">{{ $t('management.othermgmt.card_departments') }}</span>
          <span class="smgp-mgmt-card__desc">{{ $t('management.othermgmt.card_departments_desc') }}</span>
        </span>
      </a>
    </div>

    <!-- User list table -->
    <h2 class="smgp-mgmt-page__section-title">
      <i class="icon-users" aria-hidden="true" />
      {{ $t('management.users.section_users') }}
      <span class="smgp-mgmt-userlist__count">
        <template v-if="data.haslimit">
          {{ data.studentcount }} / {{ data.maxstudents }}
        </template>
        <template v-else>{{ data.usercount }}</template>
      </span>
    </h2>

    <div v-if="data.limitreached" class="alert alert-warning">
      <i class="fa fa-exclamation-triangle" />
      {{ data.limit_reached_msg || 'Student limit reached.' }}
    </div>

    <div v-if="data.hasusers" class="table-responsive">
      <table class="smgp-mgmt-table">
        <thead>
          <tr>
            <th>{{ data.th_name || 'Name' }}</th>
            <th>{{ data.th_email || 'Email' }}</th>
            <th>{{ data.th_lastaccess || 'Last access' }}</th>
            <th class="text-center">{{ data.th_actions || 'Actions' }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in data.users" :key="user.id || user.email">
            <td>{{ user.fullname }}</td>
            <td>{{ user.email }}</td>
            <td>{{ user.lastaccess }}</td>
            <td class="text-center text-nowrap">
              <a
                v-if="user.editurl"
                :href="user.editurl"
                class="btn btn-sm btn-outline-primary me-1"
              >
                <i class="icon-square-pen" />
                {{ data.edit_label || 'Edit' }}
              </a>
              <button
                v-if="user.deleteurl"
                class="btn btn-sm btn-outline-danger"
                @click="confirmDelete(user)"
              >
                <i class="icon-trash-2" />
                {{ data.delete_label || 'Delete' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="alert alert-info">
      {{ data.nousers || 'No users found.' }}
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { getCompanyUsers, deleteCompanyUser } = useManagementApi()
const authStore = useAuthStore()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

const fetchData = async () => {
  loading.value = true
  const result = await getCompanyUsers()
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
}

const confirmDelete = async (user: any) => {
  if (!confirm(data.value?.delete_confirm || `Delete user ${user.fullname}?`)) return
  const result = await deleteCompanyUser(user.id)
  if (result.error) {
    alert(result.error)
  } else {
    await fetchData()
  }
}

fetchData()
</script>

<style scoped>
.smgp-mgmt-userlist__count {
  margin-left: auto;
  font-size: 0.78rem;
  font-weight: 500;
  color: #64748b;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: 0.25rem 0.75rem;
  text-transform: none;
  letter-spacing: 0;
}
</style>
