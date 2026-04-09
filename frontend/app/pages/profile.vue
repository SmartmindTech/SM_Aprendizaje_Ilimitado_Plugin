<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="smgp-profile">
    <!-- ============================================================ -->
    <!-- Avatar + name header                                          -->
    <!-- ============================================================ -->
    <div class="smgp-profile__header">
      <img :src="data.avatarurl" :alt="data.fullname" class="smgp-profile__avatar">
      <div class="smgp-profile__header-info">
        <h1 class="smgp-profile__name">{{ data.fullname }}</h1>
        <p v-if="data.has_department" class="smgp-profile__department">
          <i class="icon-building" /> {{ data.department }}
        </p>
        <p class="smgp-profile__meta">
          <i class="icon-calendar" /> {{ $t('profile.member_since') }} {{ data.joindate }}
        </p>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- Stats                                                         -->
    <!-- ============================================================ -->
    <div class="smgp-profile__stats">
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.course_count }}</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.enrolled_courses') }}</span>
      </div>
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.completed_count }}</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.completed_courses') }}</span>
      </div>
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.total_hours }}h</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.total_hours') }}</span>
      </div>
      <div class="smgp-profile__stat">
        <span class="smgp-profile__stat-value">{{ data.streak }}</span>
        <span class="smgp-profile__stat-label">{{ $t('profile.streak_days') }}</span>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- Weekly activity chart (Chart.js via vue-chartjs)              -->
    <!-- ============================================================ -->
    <div class="smgp-profile__chart">
      <h3 class="smgp-profile__chart-title">
        {{ $t('profile.weekly_activity') }}
      </h3>
      <div class="smgp-profile__chart-canvas">
        <Bar :data="weekChartData" :options="weekChartOptions" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useMoodleAjax } from '~/composables/api_calls/useMoodleAjax'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Title,
} from 'chart.js'

// Register only the modules we actually use so the bundler can
// tree-shake the rest of Chart.js out of the build.
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Title)

definePageMeta({ middleware: ['auth'] })

const { call } = useMoodleAjax()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

// ── Weekly activity chart data ───────────────────────────────────
// Maps the backend's data.week_activity (one entry per day with
// {day, count, istoday, ispast}) into a Chart.js dataset. The colour
// of each bar is decided per-data-point so today/past/future can have
// distinct visuals without splitting into multiple datasets.
const SMGP_GREEN_TODAY  = '#059669'
const SMGP_GREEN_PAST   = '#10b981'
const SMGP_GREY_FUTURE  = '#e2e8f0'

interface WeekDay {
  day: string
  count: number
  istoday: boolean
  ispast: boolean
  height?: number
}

const weekChartData = computed(() => {
  const days: WeekDay[] = data.value?.week_activity ?? []
  return {
    labels: days.map(d => d.day),
    datasets: [
      {
        label: 'Activity',
        data: days.map(d => d.count),
        backgroundColor: days.map(d => {
          if (d.istoday) return SMGP_GREEN_TODAY
          if (!d.ispast) return SMGP_GREY_FUTURE
          return SMGP_GREEN_PAST
        }),
        borderRadius: 6,
        borderSkipped: false,
        // Cap bar thickness so 7 narrow bars look like a chart, not
        // a grid of fat blocks. maxBarThickness wins over the grid
        // calculation when the container is wide.
        maxBarThickness: 32,
      },
    ],
  }
})

const weekChartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  layout: {
    padding: { top: 10, right: 4, bottom: 0, left: 4 },
  },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#0f172a',
      titleColor: '#fff',
      bodyColor: '#fff',
      padding: 10,
      cornerRadius: 8,
      displayColors: false,
      callbacks: {
        label: (ctx: { parsed: { y: number } }) => `${ctx.parsed.y} activities`,
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: {
        color: '#64748b',
        font: { size: 12, weight: 600 as const },
      },
      border: { display: false },
    },
    y: {
      beginAtZero: true,
      grid: {
        color: '#f1f5f9',
        drawTicks: false,
      },
      ticks: {
        color: '#94a3b8',
        font: { size: 11 },
        precision: 0,
        padding: 8,
      },
      border: { display: false },
    },
  },
}))

async function fetchAll() {
  loading.value = true
  error.value = null
  const profileResult = await call('local_sm_graphics_plugin_get_profile_data', { userid: 0 })
  loading.value = false

  if (profileResult.error) {
    error.value = profileResult.error
    return
  }
  data.value = profileResult.data
}

fetchAll()
</script>

<style scoped lang="scss">
.smgp-profile {
  max-width: 960px;
  margin: 2rem auto;
  padding: 0 1rem;

  &__header {
    display: flex;
    gap: 2rem;
    align-items: center;
    padding: 2rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
  }
  &__avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e2e8f0;
  }
  &__name {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.5rem;
  }
  &__department,
  &__meta {
    color: #64748b;
    margin: 0.25rem 0;
    font-size: 0.9rem;
    i { margin-right: 0.35rem; }
  }
  &__stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
    @media (max-width: 600px) { grid-template-columns: repeat(2, 1fr); }
  }
  &__stat {
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }
  &__stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #10b981;
  }
  &__stat-label {
    display: block;
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 0.25rem;
  }
  &__chart {
    display:flex;
    flex-direction: column;
    background: #fff;
    height: auto;
    width: auto;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
  }
  // Title gets its own top + horizontal padding because the .smgp-
  // profile__chart card itself has no padding — that way the title
  // sits flush with the rest of the card content but visually
  // breathes away from the rounded corners.
  &__chart-title {
    justify-content: start;
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    padding: 1.25rem 1.5rem 0.75rem;

  }

  // Fixed-height wrapper around the Chart.js canvas. Chart.js needs a
  // parent with an explicit height when maintainAspectRatio is false,
  // otherwise the canvas grows unbounded as it tries to fit its data.
  // The padding lives on the wrapper so the chart never touches the
  // card edges; box-sizing border-box keeps the 280 px the actual
  // chart area regardless of padding.
  &__chart-canvas {
    padding-top:3rem;
    padding-bottom: 3rem;
    min-height: 250px;
    width: 80%;
    margin-inline: auto;
    padding: 1rem 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;


    // Chart.js writes inline width/height attributes onto the <canvas>
    // when it auto-sizes, and those win over normal CSS rules. The
    // !important here clamps the canvas to its parent so it can never
    // overflow horizontally or vertically — the actual chart still
    // respects the parent thanks to maintainAspectRatio: false.
  }
}
</style>
