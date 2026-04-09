<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else class="sm-companylimits p-4">
    <div class="d-flex align-items-start gap-3 mb-2">
      <button
        type="button"
        class="btn btn-outline-secondary mt-1"
        @click="$router.back()"
      >
        <i class="icon-arrow-left" />
      </button>
      <div class="flex-grow-1">
        <h2 class="mb-2">{{ $t('adminCompanyLimits.heading') }}</h2>
        <p class="text-muted mb-4">{{ $t('adminCompanyLimits.help') }}</p>
      </div>
    </div>

    <template v-if="companies.length">
      <form @submit.prevent="saveAll">
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>{{ $t('adminCompanyLimits.th_company') }}</th>
                <th>{{ $t('adminCompanyLimits.th_shortname') }}</th>
                <th class="text-center">{{ $t('adminCompanyLimits.th_students') }}</th>
                <th class="text-center">{{ $t('adminCompanyLimits.th_maxlimit') }}</th>
                <th class="text-center">{{ $t('adminCompanyLimits.th_status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="company in companies" :key="company.id">
                <td>{{ company.name }}</td>
                <td class="text-muted">{{ company.shortname }}</td>
                <td class="text-center">{{ company.currentstudents }}</td>
                <td class="text-center" style="width:140px">
                  <input
                    v-model.number="editLimits[company.id]"
                    type="number"
                    min="0"
                    class="form-control form-control-sm text-center"
                  >
                </td>
                <td class="text-center">
                  <span v-if="company.status === 'unlimited'" class="badge bg-secondary">
                    {{ $t('adminCompanyLimits.unlimited_label') }}
                  </span>
                  <span v-else-if="company.limitreached" class="badge bg-danger">
                    {{ $t('adminCompanyLimits.full_label') }}
                  </span>
                  <span v-else class="badge bg-success">
                    {{ $t('adminCompanyLimits.ok_label') }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="spinner-border spinner-border-sm me-1" />
          <i v-else class="fa fa-save" />
          {{ $t('adminCompanyLimits.save_label') }}
        </button>
      </form>
    </template>

    <div v-else class="alert alert-info">{{ $t('adminCompanyLimits.no_companies') }}</div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

// Shape returned by local_sm_graphics_plugin_get_company_limits (see
// classes/external/get_company_limits.php). Kept here as a local type
// so the template can read the actual field names rather than the
// legacy mustache ones (companyid/companyname/studentcount/unlimited).
interface CompanyLimit {
  id: number
  name: string
  shortname: string
  currentstudents: number
  maxstudents: number
  limitreached: boolean
  status: 'ok' | 'full' | 'unlimited'
}

const { getCompanyLimits, updateCompanyLimit } = useAdminApi()

const loading = ref(true)
const error = ref<string | null>(null)
const companies = ref<CompanyLimit[]>([])
const editLimits = ref<Record<number, number>>({})
const saving = ref(false)

const fetchData = async () => {
  loading.value = true
  const result = await getCompanyLimits()
  loading.value = false
  if (result.error) {
    error.value = result.error
    return
  }
  companies.value = (result.data?.companies ?? []) as CompanyLimit[]
  editLimits.value = {}
  for (const company of companies.value) {
    editLimits.value[company.id] = company.maxstudents
  }
}

const saveAll = async () => {
  saving.value = true
  for (const company of companies.value) {
    const newLimit = editLimits.value[company.id]
    if (newLimit !== company.maxstudents) {
      const result = await updateCompanyLimit(company.id, newLimit ?? 0)
      if (result.error) {
        alert(result.error)
        saving.value = false
        return
      }
    }
  }
  saving.value = false
  await fetchData()
}

fetchData()
</script>
