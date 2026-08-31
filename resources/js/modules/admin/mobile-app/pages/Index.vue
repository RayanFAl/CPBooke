<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import AdminButton from '../../components/AdminButton.vue';
import AdminInput from '../../components/AdminInput.vue';
import { useAdminLocale } from '../../composables/useAdminLocale';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    release: { type: Object, default: null },
    manifest: { type: Object, required: true },
    apk_files: { type: Array, default: () => [] },
    download_page_url: { type: String, required: true },
    download_file_url: { type: String, required: true },
    update_check_url: { type: String, required: true },
    upload_url: { type: String, required: true },
    expected_filename: { type: String, required: true },
    upload_limits: { type: Object, required: true },
});

const { t, forwardArrow } = useAdminLocale();
const page = usePage();
const apkInput = ref(null);

const flashSuccess = computed(() => page.props.flash?.success ?? '');

const uploadForm = useForm({
    apk: null,
    version: props.manifest.version ?? '1.0.0',
    version_code: props.manifest.version_code ?? 1,
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
};

const submitUpload = () => {
    if (selectedApkTooLarge.value) {
        return;
    }

    uploadForm.post(props.upload_url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset('apk');
            if (apkInput.value) {
                apkInput.value.value = '';
            }
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
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ t('Platform') }}</p>
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
                        </div>

                        <p><span class="font-medium text-slate-900">{{ t('APK file') }}:</span> {{ release.apk_filename }}</p>
                        <p><span class="font-medium text-slate-900">{{ t('File size') }}:</span> {{ formatBytes(release.file_size) }}</p>
                        <p v-if="release.published_at"><span class="font-medium text-slate-900">{{ t('Published') }}:</span> {{ release.published_at }}</p>
                        <p v-if="release.sha256" class="break-all text-xs text-slate-500"><span class="font-medium text-slate-700">SHA-256:</span> {{ release.sha256 }}</p>

                        <div class="space-y-2 pt-2">
                            <a :href="download_page_url" target="_blank" rel="noopener noreferrer" class="block text-cyan-700 hover:underline">
                                {{ t('Public download page') }} {{ forwardArrow }}
                            </a>
                            <a :href="download_file_url" target="_blank" rel="noopener noreferrer" class="block text-cyan-700 hover:underline">
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
                        {{ t('PHP upload limit is too low for large APK files. Restart with composer dev or run: php artisan mobile-app:import-apk path/to/app.apk') }}
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
                            <span class="mb-1 block font-medium text-slate-800">{{ t('APK file') }}</span>
                            <input
                                ref="apkInput"
                                type="file"
                                accept=".apk,application/vnd.android.package-archive"
                                class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm"
                                @change="onApkSelected"
                            />
                            <p v-if="uploadForm.errors.apk" class="mt-1 text-sm text-rose-600">{{ uploadForm.errors.apk }}</p>
                            <p v-else-if="selectedApkTooLarge" class="mt-1 text-sm text-rose-600">
                                {{ t('The selected APK is too large.') }} ({{ formatBytes(uploadForm.apk?.size) }} / {{ formatBytes(effectiveMaxBytes) }})
                            </p>
                        </label>

                        <AdminButton type="submit" :disabled="uploadForm.processing || selectedApkTooLarge || phpUploadTooLow">
                            {{ uploadForm.processing ? t('Uploading…') : t('Upload APK') }}
                        </AdminButton>
                    </form>
                </section>
            </div>

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
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="file in apk_files" :key="file.filename" class="border-b border-slate-100">
                                <td class="px-3 py-3 font-medium text-slate-900">{{ file.filename }}</td>
                                <td class="px-3 py-3">{{ file.version ?? '—' }}</td>
                                <td class="px-3 py-3">{{ file.version_code ?? '—' }}</td>
                                <td class="px-3 py-3">{{ formatBytes(file.size) }}</td>
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
