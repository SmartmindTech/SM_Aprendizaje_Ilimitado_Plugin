<template>
  <div class="smgp-profile-chart">
    <h3 class="smgp-profile-chart__title">
      {{ $t('profile.weekly_activity') }}
    </h3>
    <div class="smgp-profile-chart__canvas">
      <Bar :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Title,
} from 'chart.js'
import type { ProfileWeekDay } from '~/types/profile'

// Register only the modules we actually use so the bundler can
// tree-shake the rest of Chart.js out of the build.
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Title)

const props = defineProps<{ days: ProfileWeekDay[] }>()

const SMGP_GREEN_TODAY = '#059669'
const SMGP_GREEN_PAST  = '#10b981'
const SMGP_GREY_FUTURE = '#e2e8f0'

const chartData = computed(() => {
  const days = props.days ?? []
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
        maxBarThickness: 32,
      },
    ],
  }
})

const chartOptions = computed(() => ({
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
      grace: '5%'
    },
  },
}))
</script>

<style scoped lang="scss">
.smgp-profile-chart {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;

  &__title {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
  }
  &__canvas {
    flex: 1;
    min-height: 220px;
    padding-top: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}
</style>
