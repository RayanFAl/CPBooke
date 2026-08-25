<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    image: { type: [File, Object], default: null },
    imageUrl: { type: String, default: '' },
    currentImageUrl: { type: String, default: '' },
    hint: { type: String, default: '' },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:image', 'update:imageUrl']);

const { t } = useAdminLocale();
const fileInput = ref(null);
const isDragging = ref(false);
const localPreview = ref('');

const selectedFileName = computed(() => {
    if (props.image && typeof props.image.name === 'string') {
        return props.image.name;
    }

    return '';
});

const previewSrc = computed(() => localPreview.value || props.currentImageUrl || '');

watch(
    () => props.image,
    (file) => {
        if (localPreview.value) {
            URL.revokeObjectURL(localPreview.value);
            localPreview.value = '';
        }

        if (file instanceof File) {
            localPreview.value = URL.createObjectURL(file);
        }
    },
);

onBeforeUnmount(() => {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
    }
});

const openPicker = () => {
    fileInput.value?.click();
};

const assignFile = (file) => {
    if (!file || !file.type?.startsWith('image/')) {
        return;
    }

    emit('update:image', file);
};

const onFileChange = (event) => {
    assignFile(event.target.files?.[0] ?? null);
};

const onDrop = (event) => {
    isDragging.value = false;
    assignFile(event.dataTransfer?.files?.[0] ?? null);
};

const clearFile = () => {
    emit('update:image', null);

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};
</script>

<template>
    <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-sm font-medium text-slate-900">{{ label || t('Upload image') }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ hint || t('Prefer WebP/JPG ~1600px wide.') }}</p>
            </div>
            <button
                v-if="image"
                type="button"
                class="text-xs font-medium text-rose-600 hover:underline"
                @click="clearFile"
            >
                {{ t('Remove selected file') }}
            </button>
        </div>

        <div
            class="relative overflow-hidden rounded-2xl border-2 border-dashed transition"
            :class="isDragging
                ? 'border-cyan-500 bg-cyan-50'
                : 'border-slate-300 bg-white hover:border-slate-400'"
            @dragenter.prevent="isDragging = true"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="onFileChange"
            >

            <div v-if="previewSrc" class="relative">
                <img :src="previewSrc" alt="" class="h-44 w-full object-cover">
                <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-3 bg-gradient-to-t from-slate-950/70 to-transparent px-4 py-3">
                    <p class="truncate text-xs text-white">
                        {{ selectedFileName || t('Current image') }}
                    </p>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg bg-white/95 px-3 py-1.5 text-xs font-medium text-slate-800"
                        @click="openPicker"
                    >
                        {{ t('Change image') }}
                    </button>
                </div>
            </div>

            <button
                v-else
                type="button"
                class="flex w-full flex-col items-center justify-center gap-2 px-4 py-10 text-center"
                @click="openPicker"
            >
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                </span>
                <span class="text-sm font-medium text-slate-800">{{ t('Click to upload or drag and drop') }}</span>
                <span class="text-xs text-slate-500">JPG, PNG, WebP</span>
            </button>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-800">{{ t('Or CDN image URL') }}</label>
            <input
                :value="imageUrl"
                type="url"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                placeholder="https://cdn.example.com/..."
                @input="emit('update:imageUrl', $event.target.value)"
            >
            <p class="mt-1 text-xs text-slate-500">{{ t('Use either upload or URL, not both.') }}</p>
        </div>

        <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
