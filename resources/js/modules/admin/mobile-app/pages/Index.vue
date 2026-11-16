<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminButton from '../../components/AdminButton.vue';
import AdminInput from '../../components/AdminInput.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    release: { type: Object, default: null },
    manifest: { type: Object, required: true },
    apk_files: { type: Array, default: () => [] },
    download_page_url: { type: String, required: true },
    download_file_url: { type: String, required: true },
    update_check_url: { type: String, required: true },
    upload_url: { type: String, required: true },
    apk_delete_url: { type: String, required: true },
    release_update_url: { type: String, required: true },
    expected_filename: { type: String, required: true },
    upload_limits: { type: Object, required: true },
});

const { t, forwardArrow } = useAdminLocale();
const page = usePage();
const apkInput = ref(null);
const uploadProgress = ref(null);

const flashSuccess = computed(() => page.props.flash?.success ?? '');

const uploadForm = useForm({
    apk: null,
    version: props.manifest.version ?? '1.0.0',
    version_code: props.manifest.version_code ?? 1,
    notify_users: true,
});

const showUploadProgress = computed(() => uploadForm.processing || uploadProgress.value !== null);
const uploadProgressPercent = computed(() => Math.min(100, Math.max(0, Number(uploadProgress.value ?? 0))));
const uploadProgressLabel = computed(() => {
    if (!uploadForm.processing && uploadProgress.value === null) {
        return '';
    }

    if (uploadProgressPercent.value >= 100 && uploadForm.processing) {
        return t('Processing upload…');
    }

    return `${t('Uploading…')} ${uploadProgressPercent.value}%`;
});

const releaseForm = useForm({
    version: props.manifest.version ?? '1.0.0',
    version_code: props.manifest.version_code ?? 1,
    apk: props.manifest.apk ?? '',
    force_update: Boolean(props.manifest.force_update),
    notify_users: false,
    min_version_code: props.manifest.min_version_code ?? '',
    notes_ar: props.manifest.notes_ar ?? '',
    notes_en: props.manifest.notes_en ?? '',
});

const expectedUploadFilename = computed(() => {
    if (!uploadForm.version || !uploadForm.version_code) {
        return props.expected_filename;
    }

    return `booke-${uploadForm.version}+${Number(uploadForm.version_code)}.apk`;
});

const effectiveMaxBytes = computed(() => Number(props.upload_limits.effective_max_kb ?? 0) * 1024);
const phpUploadTooLow = computed(() => !props.upload_limits.php_ready);
const selectedApkTooLarge = computed(() => (
    uploadForm.apk instanceof File && uploadForm.apk.size > effectiveMaxBytes.value
));

const formatBytes = (bytes) => {
    if (!bytes || Number(bytes) <= 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = Number(bytes);
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
};

const onApkSelected = (event) => {
    uploadForm.apk = event.target.files?.[0] ?? null;
    uploadProgress.value = null;
};

const submitUpload = () => {
    if (selectedApkTooLarge.value) {
        return;
    }

    uploadProgress.value = 0;

    uploadForm
        .transform((data) => ({
            ...data,
            notify_users: Boolean(data.notify_users),
        }))
        .post(props.upload_url, {
            forceFormData: true,
            preserveScroll: true,
            showProgress: false,
            onProgress: (progress) => {
                uploadProgress.value = typeof progress?.percentage === 'number'
                    ? progress.percentage
                    : 0;
            },
            onSuccess: () => {
                uploadProgress.value = 100;
                uploadForm.reset('apk');
                uploadForm.notify_users = true;
                if (apkInput.value) {
                    apkInput.value.value = '';
                }
            },
            onFinish: () => {
                window.setTimeout(() => {
                    if (!uploadForm.processing) {
                        uploadProgress.value = null;
                    }
                }, 600);
            },
        });
};

const useApkInSettings = (file) => {
    releaseForm.apk = file.filename;
    if (file.version) {
        releaseForm.version = file.version;
    }
    if (file.version_code) {
        releaseForm.version_code = file.version_code;
    }
};

const deletingFilename = ref(null);

const deleteApk = (file) => {
    const isActive = props.manifest?.apk === file.filename;
    const message = isActive
        ? t('Delete this APK? It is the active release. The next highest version will become active if available.')
        : t('Delete this APK permanently?');

    if (!window.confirm(message)) {
        return;
    }

    deletingFilename.value = file.filename;

    router.delete(props.apk_delete_url, {
        data: { filename: file.filename },
        preserveScroll: true,
        onFinish: () => {
            deletingFilename.value = null;
        },
    });
};

const submitReleaseSettings = () => {
    releaseForm
        .transform((data) => ({
            ...data,
            min_version_code: data.min_version_code === '' || data.min_version_code === null
                ? null
                : Number(data.min_version_code),
            force_update: Boolean(data.force_update),
            notify_users: Boolean(data.notify_users),
        }))
        .put(props.release_update_url, {
            preserveScroll: true,
            onSuccess: () => {
                releaseForm.notify_users = false;
            },
        });
};
</script>

<template>
    <AdminLayout
        title="Mobile App"
        description="Upload Android APK releases and share public download links."
    >
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#2f6db8]">{{ t('Platform') }}</p>
                <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ t('Mobile App') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ t('Upload APK builds for Android sideloading. The latest version_code is served automatically to the mobile app update API and the public download page.') }}
                </p>
                <p v-if="flashSuccess" class="mt-4 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ flashSuccess }}
                </p>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Current release') }}</h3>

                    <div v-if="release" class="mt-4 space-y-3 text-sm text-slate-700">
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                {{ release.version }} · +{{ release.version_code }}
                            </span>
                            <span
                                v-if="release.force_update"
                                class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800"
                            >
                                {{ t('Force update') }}
                            </span>
                            <span
                                v-if="release.min_version_code"
                                class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900"
                            >
                                {{ t('Minimum supported version code') }}: {{ release.min_version_code }}
                            </span>
                        </div>

                        <p><span class="font-medium text-slate-900">{{ t('APK file') }}:</span> {{ release.apk_filename }}</p>
                        <p><span class="font-medium text-slate-900">{{ t('File size') }}:</span> {{ formatBytes(release.file_size) }}</p>
                        <p v-if="release.published_at"><span class="font-medium text-slate-900">{{ t('Published') }}:</span> {{ release.published_at }}</p>
                        <p v-if="release.sha256" class="break-all text-xs text-slate-500"><span class="font-medium text-slate-700">SHA-256:</span> {{ release.sha256 }}</p>

                        <div class="space-y-2 pt-2">
                            <a :href="download_page_url" target="_blank" rel="noopener noreferrer" class="block text-[#2f6db8] hover:underline">
                                {{ t('Public download page') }} {{ forwardArrow }}
                            </a>
                            <a :href="download_file_url" target="_blank" rel="noopener noreferrer" class="block text-[#2f6db8] hover:underline">
                                {{ t('Direct APK download') }} {{ forwardArrow }}
                            </a>
                            <p class="text-xs text-slate-500">{{ t('Update API') }}: {{ update_check_url }}</p>
                        </div>
                    </div>

                    <p v-else class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ t('No APK has been uploaded yet. Upload the first release below.') }}
                    </p>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-950">{{ t('Upload new APK') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ t('Use semantic version and version_code. The file will be saved as') }}
                        <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ expectedUploadFilename }}</code>
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        {{ t('Maximum upload size') }}: {{ formatBytes(effectiveMaxBytes) }}
                        <span v-if="upload_limits.php_upload_max_label"> · PHP {{ upload_limits.php_upload_max_label }}</span>
                    </p>
                    <p v-if="phpUploadTooLow" class="mt-3 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        {{ t('PHP upload limit is below 128 MB, so larger APKs may fail. Raise upload_max_filesize and post_max_size in php.ini (or config/php/local-dev.ini for local), restart PHP / php artisan serve, then refresh. Files within the limit above can still be uploaded.') }}
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="submitUpload">
                        <AdminInput
                            v-model="uploadForm.version"
                            :label="t('Version')"
                            placeholder="1.2.0"
                            :error="uploadForm.errors.version"
                        />
                        <AdminInput
                            v-model="uploadForm.version_code"
                            :label="t('Version code')"
                            type="number"
                            min="1"
                            :error="uploadForm.errors.version_code"
                        />

                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-800">{{ t('APK or ZIP file') }}</span>
                            <input
                                ref="apkInput"
                                type="file"
                                accept=".apk,.zip,application/vnd.android.package-archive,application/zip"
                                class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm"
                                @change="onApkSelected"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                {{ t('You can upload an .apk directly, or a .zip that contains one APK. The server extracts it automatically.') }}
                            </p>
                            <p v-if="uploadForm.errors.apk" class="mt-1 text-sm text-rose-600">{{ uploadForm.errors.apk }}</p>
                            <p v-else-if="selectedApkTooLarge" class="mt-1 text-sm text-rose-600">
                                {{ t('The selected file is too large.') }} ({{ formatBytes(uploadForm.apk?.size) }} / {{ formatBytes(effectiveMaxBytes) }})
                            </p>
                        </label>

                        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                            <input
                                v-model="uploadForm.notify_users"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-[#2f6db8] focus:ring-[#2f6db8]"
                            >
                            <span>
                                <span class="font-medium">{{ t('Notify users') }}</span>
                                <span class="mt-1 block text-xs text-slate-500">
                                    {{ t('Send a push notification so users can download the new APK immediately.') }}
                                </span>
                            </span>
                        </label>

                        <div
                            v-if="showUploadProgress"
                            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                            role="progressbar"
                            :aria-valuenow="uploadProgressPercent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="uploadProgressLabel"
                        >
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-800">{{ uploadProgressLabel }}</span>
                                <span class="tabular-nums font-semibold text-[#2f6db8]">{{ uploadProgressPercent }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div
                                    class="h-full rounded-full bg-[#2f6db8] transition-[width] duration-200 ease-out"
                                    :style="{ width: `${uploadProgressPercent}%` }"
                                />
                            </div>
                            <p v-if="uploadForm.apk?.size" class="mt-2 text-xs text-slate-500">
                                {{ formatBytes(uploadForm.apk.size) }}
                            </p>
                        </div>

                        <AdminButton type="submit" :disabled="uploadForm.processing || selectedApkTooLarge">
                            {{ uploadForm.processing ? `${t('Uploading…')} ${uploadProgressPercent}%` : t('Upload APK') }}
                        </AdminButton>
                    </form>
                </section>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Release settings') }}</h3>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('Edit release.json metadata used by the update dialog and public download page.') }}
                </p>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('You can update Arabic/English release notes anytime after upload, then save.') }}
                </p>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('Turn on force update to block older app builds until users download the latest APK.') }}
                </p>

                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="submitReleaseSettings">
                    <AdminInput
                        v-model="releaseForm.version"
                        :label="t('Version')"
                        placeholder="1.2.0"
                        :error="releaseForm.errors.version"
                    />
                    <AdminInput
                        v-model="releaseForm.version_code"
                        :label="t('Version code')"
                        type="number"
                        min="1"
                        :error="releaseForm.errors.version_code"
                    />
                    <div class="md:col-span-2">
                        <AdminInput
                            v-model="releaseForm.apk"
                            :label="t('Active APK filename')"
                            placeholder="booke-1.2.0+120.apk"
                            :error="releaseForm.errors.apk"
                        />
                    </div>
                    <AdminInput
                        v-model="releaseForm.min_version_code"
                        :label="t('Minimum supported version code')"
                        type="number"
                        min="1"
                        :error="releaseForm.errors.min_version_code"
                    />
                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                        <input
                            v-model="releaseForm.force_update"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-[#2f6db8] focus:ring-[#2f6db8]"
                        >
                        <span>
                            <span class="font-medium">{{ t('Force update') }}</span>
                            <span class="mt-1 block text-xs text-slate-500">
                                {{ t('When enabled, the mobile app must update before the user can continue.') }}
                            </span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                        <input
                            v-model="releaseForm.notify_users"
                            type="checkbox"
                            class="mt-0.5 rounded border-slate-300 text-[#2f6db8] focus:ring-[#2f6db8]"
                        >
                        <span>
                            <span class="font-medium">{{ t('Notify users') }}</span>
                            <span class="mt-1 block text-xs text-slate-500">
                                {{ t('Also send a push notification about this release after saving.') }}
                            </span>
                        </span>
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Release notes (Arabic)') }}</span>
                        <textarea
                            v-model="releaseForm.notes_ar"
                            rows="3"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm"
                        />
                        <p v-if="releaseForm.errors.notes_ar" class="mt-1 text-sm text-rose-600">{{ releaseForm.errors.notes_ar }}</p>
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="mb-1 block font-medium text-slate-800">{{ t('Release notes (English)') }}</span>
                        <textarea
                            v-model="releaseForm.notes_en"
                            rows="3"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm"
                        />
                        <p v-if="releaseForm.errors.notes_en" class="mt-1 text-sm text-rose-600">{{ releaseForm.errors.notes_en }}</p>
                    </label>
                    <div class="md:col-span-2">
                        <AdminButton type="submit" :disabled="releaseForm.processing || !apk_files.length">
                            {{ releaseForm.processing ? t('Saving…') : t('Save release settings') }}
                        </AdminButton>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-950">{{ t('Stored APK files') }}</h3>
                <p class="mt-2 text-sm text-slate-600">
                    {{ t('All APK files found in storage/app/releases. The highest version_code is served automatically.') }}
                </p>

                <div v-if="apk_files.length" class="mt-4 overflow-x-auto">
                    <table class="admin-data-table min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-slate-500">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ t('Filename') }}</th>
                                <th class="px-3 py-2 font-medium">{{ t('Version') }}</th>
                                <th class="px-3 py-2 font-medium">{{ t('Version code') }}</th>
                                <th class="px-3 py-2 font-medium">{{ t('File size') }}</th>
                                <th class="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="file in apk_files" :key="file.filename" class="border-b border-slate-100">
                                <td class="px-3 py-3 font-medium text-slate-900">{{ file.filename }}</td>
                                <td class="px-3 py-3">{{ file.version ?? '—' }}</td>
                                <td class="px-3 py-3">{{ file.version_code ?? '—' }}</td>
                                <td class="px-3 py-3">{{ formatBytes(file.size) }}</td>
                                <td class="px-3 py-3 text-end">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-[#2f6db8] transition hover:bg-[#2f6db8]/10"
                                            :title="t('Use in release settings')"
                                            :aria-label="t('Use in release settings')"
                                            @click="useApkInSettings(file)"
                                        >
                                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-rose-600 transition hover:bg-rose-50 disabled:opacity-50"
                                            :title="t('Delete')"
                                            :aria-label="t('Delete')"
                                            :disabled="deletingFilename === file.filename"
                                            @click="deleteApk(file)"
                                        >
                                            <svg
                                                v-if="deletingFilename === file.filename"
                                                class="h-5 w-5 animate-spin"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                aria-hidden="true"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                            </svg>
                                            <svg
                                                v-else
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                class="h-5 w-5"
                                                aria-hidden="true"
                                            >
                                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75V4h-.75A2.25 2.25 0 003 6.25v.5c0 .414.336.75.75.75h12.5a.75.75 0 00.75-.75v-.5A2.25 2.25 0 0014.75 4H14v-.25A2.75 2.75 0 0011.25 1h-2.5zM6.5 4v-.25c0-.69.56-1.25 1.25-1.25h2.5c.69 0 1.25.56 1.25 1.25V4h-5z" clip-rule="evenodd" />
                                                <path d="M5.5 7.5A.75.75 0 016.25 6.75h7.5a.75.75 0 01.75.75v7A2.75 2.75 0 0111.75 17.25h-3.5A2.75 2.75 0 015.5 14.5v-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    {{ t('No APK files found yet.') }}
                </p>
            </section>
        </section>
    </AdminLayout>
</template>
