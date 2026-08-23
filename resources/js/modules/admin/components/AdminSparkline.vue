<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    points: {
        type: Array,
        default: () => [],
    },
    linePath: {
        type: String,
        default: '',
    },
    gradientId: {
        type: String,
        required: true,
    },
    format: {
        type: String,
        default: 'number',
    },
    locale: {
        type: String,
        default: 'en',
    },
    currency: {
        type: String,
        default: 'LYD',
    },
    strokeFrom: {
        type: String,
        default: '#60a5fa',
    },
    strokeTo: {
        type: String,
        default: '#94a3b8',
    },
});

const emit = defineEmits(['point-click']);

const hoveredIndex = ref(-1);

const chartPoints = computed(() => {
    const values = props.points.map((point) => Number(point.value ?? 0));
    const width = 320;
    const height = 120;
    const maxValue = Math.max(...values, 1);
    const stepX = values.length > 1 ? width / (values.length - 1) : width;

    return props.points.map((point, index) => ({
        ...point,
        x: Number((index * stepX).toFixed(2)),
        y: Number((height - ((Number(point.value ?? 0) / maxValue) * height)).toFixed(2)),
    }));
});

const formatValue = (value) => {
    if (props.format === 'currency') {
        return new Intl.NumberFormat(props.locale, {
            style: 'currency',
            currency: props.currency || 'LYD',
            maximumFractionDigits: 0,
        }).format(Number(value ?? 0));
    }

    return new Intl.NumberFormat(props.locale).format(Number(value ?? 0));
};

const tooltip = computed(() => {
    if (hoveredIndex.value < 0) {
        return null;
    }

    const point = chartPoints.value[hoveredIndex.value];

    if (!point) {
        return null;
    }

    return {
        label: point.label,
        value: formatValue(point.value),
        x: point.x,
        y: point.y,
    };
});
</script>

<template>
    <div class="relative">
        <svg viewBox="0 0 320 140" class="h-40 w-full cursor-pointer">
            <defs>
                <linearGradient :id="gradientId" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" :stop-color="strokeFrom" />
                    <stop offset="100%" :stop-color="strokeTo" />
                </linearGradient>
            </defs>
            <path d="M 0 120 L 320 120" stroke="rgba(148,163,184,0.28)" stroke-width="1" />
            <path
                v-if="linePath"
                :d="linePath"
                fill="none"
                :stroke="`url(#${gradientId})`"
                stroke-linecap="round"
                stroke-width="6"
            />
            <circle
                v-for="(point, index) in chartPoints"
                :key="`${point.label}-${index}`"
                :cx="point.x"
                :cy="point.y"
                r="8"
                fill="transparent"
                @mouseenter="hoveredIndex = index"
                @mouseleave="hoveredIndex = -1"
                @click="emit('point-click', point)"
            />
            <circle
                v-for="(point, index) in chartPoints"
                :key="`dot-${point.label}-${index}`"
                :cx="point.x"
                :cy="point.y"
                :r="hoveredIndex === index ? 5 : 3.5"
                :fill="hoveredIndex === index ? '#0891b2' : '#64748b'"
                class="pointer-events-none transition-all"
            />
        </svg>

        <div
            v-if="tooltip"
            class="pointer-events-none absolute z-10 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg"
            :style="{
                left: `${(tooltip.x / 320) * 100}%`,
                top: `${(tooltip.y / 140) * 100}%`,
                transform: 'translate(-50%, -120%)',
            }"
        >
            <p class="font-semibold text-slate-950">{{ tooltip.label }}</p>
            <p class="mt-1 text-slate-600">{{ tooltip.value }}</p>
        </div>
    </div>
</template>
