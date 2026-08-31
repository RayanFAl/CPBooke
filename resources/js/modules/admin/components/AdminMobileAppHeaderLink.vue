<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useAdminLocale } from '../composables/useAdminLocale';

const page = usePage();
const { t } = useAdminLocale();

const mobileApp = computed(() => page.props.mobileApp ?? {});
const downloadAvailable = computed(() => Boolean(mobileApp.value.available));
const downloadHref = computed(() => (
    downloadAvailable.value
        ? mobileApp.value.download_url
        : mobileApp.value.page_url
));
const linkTitle = computed(() => (
    downloadAvailable.value
        ? t('Download mobile app')
        : t('Mobile app download is not available yet')
));
</script>

<template>
    <a
        :href="downloadHref"
        :download="downloadAvailable ? '' : undefined"
        :target="downloadAvailable ? '_self' : '_blank'"
        rel="noopener noreferrer"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100 hover:text-slate-950"
        :class="downloadAvailable ? '' : 'opacity-60'"
        :title="linkTitle"
        :aria-label="linkTitle"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M7 2.75h6A2.25 2.25 0 0 1 15.25 5v10A2.25 2.25 0 0 1 13 17.25H7A2.25 2.25 0 0 1 4.75 15V5A2.25 2.25 0 0 1 7 2.75Z"
            />
            <path stroke-linecap="round" d="M9.25 15.25h1.5" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6.5v4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.25 10 11.25l1.75-2" />
        </svg>
        <span>{{ t('App') }}</span>
    </a>
</template>
