<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { computed } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    modelValue: {
        type: Array,
        required: true,
    },
    permissionGroups: {
        type: Array,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useAdminLocale();

const selectedPermissions = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const isChecked = (permissionName) => selectedPermissions.value.includes(permissionName);

const togglePermission = (permissionName) => {
    if (props.disabled) {
        return;
    }

    if (isChecked(permissionName)) {
        selectedPermissions.value = selectedPermissions.value.filter((name) => name !== permissionName);
        return;
    }

    selectedPermissions.value = [...selectedPermissions.value, permissionName];
};

const toggleModule = (permissions) => {
    if (props.disabled) {
        return;
    }

    const permissionNames = permissions.map((permission) => permission.name);
    const allSelected = permissionNames.every((name) => isChecked(name));

    if (allSelected) {
        selectedPermissions.value = selectedPermissions.value.filter((name) => !permissionNames.includes(name));
        return;
    }

    selectedPermissions.value = [...new Set([...selectedPermissions.value, ...permissionNames])];
};

const moduleSelectionLabel = (permissions) => {
    const permissionNames = permissions.map((permission) => permission.name);
    const selectedCount = permissionNames.filter((name) => isChecked(name)).length;

    if (selectedCount === 0) {
        return t('Select all');
    }

    if (selectedCount === permissionNames.length) {
        return t('Clear module');
    }

    return t('Select all');
};
</script>

<template>
    <div>
        <InputLabel :value="t('Permissions')" />
        <p class="mt-2 text-sm leading-6 text-slate-600">
            {{ disabled
                ? t('Super admins receive every permission automatically.')
                : t('Choose the exact module permissions this account should have. Changing the role reloads its default template.') }}
        </p>

        <div class="mt-4 space-y-4">
            <div
                v-for="group in permissionGroups"
                :key="group.module"
                class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h4 class="text-sm font-semibold text-slate-950">{{ t(group.label) }}</h4>
                        <p class="text-xs text-slate-500">{{ group.permissions.length }} {{ t('permissions') }}</p>
                    </div>
                    <button
                        type="button"
                        class="text-xs font-medium text-cyan-700 transition hover:text-cyan-900 disabled:cursor-not-allowed disabled:text-slate-400"
                        :disabled="disabled"
                        @click="toggleModule(group.permissions)"
                    >
                        {{ moduleSelectionLabel(group.permissions) }}
                    </button>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <label
                        v-for="permission in group.permissions"
                        :key="permission.name"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300"
                        :class="{ 'cursor-not-allowed opacity-70': disabled }"
                    >
                        <input
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                            :checked="isChecked(permission.name)"
                            :disabled="disabled"
                            @change="togglePermission(permission.name)"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-950">{{ t(permission.label) }}</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">{{ t(permission.description) }}</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <InputError class="mt-2" :message="error" />
    </div>
</template>
