<script setup>
import { computed, reactive, watch } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    actionType: { type: String, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useAdminLocale();

const SCHEMAS = {
    search_flights: [
        { key: 'origin', input: 'text', example: 'MJI' },
        { key: 'destination', input: 'text', example: 'IST' },
        { key: 'trip_type', input: 'select', options: ['oneWay', 'roundTrip'], example: 'oneWay' },
        { key: 'depart_date', input: 'date', example: '2026-09-15' },
        { key: 'adults', input: 'number', example: '1' },
        { key: 'travel_class', input: 'select', options: ['Economy', 'Business', 'First'], example: 'Economy' },
    ],
    search_hotels: [
        { key: 'city', input: 'text', example: 'Istanbul' },
        { key: 'check_in', input: 'date', example: '2026-09-15' },
        { key: 'nights', input: 'number', example: '3' },
    ],
    search_insurance: [
        { key: 'destination', input: 'text', example: 'TR' },
        { key: 'start_date', input: 'date', example: '2026-09-15' },
        { key: 'days', input: 'number', example: '7' },
    ],
};

const fields = reactive({});
let syncingFromParent = false;

const schema = computed(() => SCHEMAS[props.actionType] ?? []);

const fieldLabel = (key) => {
    const translated = t(`home.payload.${key}`);

    return translated === `home.payload.${key}` ? key : translated;
};

const emptyValues = (schemaFields) => {
    const values = {};

    schemaFields.forEach((field) => {
        values[field.key] = '';
    });

    return values;
};

const parseModelValue = (raw, schemaFields) => {
    const values = emptyValues(schemaFields);

    if (!raw || !String(raw).trim()) {
        return values;
    }

    try {
        const parsed = JSON.parse(raw);

        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return values;
        }

        schemaFields.forEach((field) => {
            if (parsed[field.key] === undefined || parsed[field.key] === null) {
                return;
            }

            values[field.key] = String(parsed[field.key]);
        });
    } catch {
        // Keep empty values when existing JSON is invalid.
    }

    return values;
};

const serializeFields = (schemaFields) => {
    const payload = {};

    schemaFields.forEach((field) => {
        const raw = String(fields[field.key] ?? '').trim();

        if (raw === '') {
            return;
        }

        if (field.input === 'number') {
            const numeric = Number(raw);
            payload[field.key] = Number.isFinite(numeric) ? numeric : raw;
            return;
        }

        payload[field.key] = raw;
    });

    return Object.keys(payload).length === 0 ? '' : JSON.stringify(payload);
};

const applyValues = (schemaFields, next) => {
    const allowed = new Set(schemaFields.map((field) => field.key));

    Object.keys(fields).forEach((key) => {
        if (!allowed.has(key)) {
            delete fields[key];
        }
    });

    schemaFields.forEach((field) => {
        fields[field.key] = next[field.key] ?? '';
    });
};

const applyExampleValues = () => {
    const next = {};

    schema.value.forEach((field) => {
        next[field.key] = String(field.example ?? '');
    });

    applyValues(schema.value, next);
};

watch(
    () => props.actionType,
    (actionType, previous) => {
        const nextSchema = SCHEMAS[actionType] ?? [];

        if (previous !== undefined && previous !== actionType) {
            applyValues(nextSchema, emptyValues(nextSchema));
            emit('update:modelValue', '');
            return;
        }

        syncingFromParent = true;
        applyValues(nextSchema, parseModelValue(props.modelValue, nextSchema));
        syncingFromParent = false;
    },
    { immediate: true },
);

watch(
    () => props.modelValue,
    (raw) => {
        if (syncingFromParent || schema.value.length === 0) {
            return;
        }

        // Avoid clobbering in-progress edits when parent echoes our own emit.
        if (serializeFields(schema.value) === (raw ?? '')) {
            return;
        }

        syncingFromParent = true;
        applyValues(schema.value, parseModelValue(raw, schema.value));
        syncingFromParent = false;
    },
);

watch(
    fields,
    () => {
        if (syncingFromParent || schema.value.length === 0) {
            return;
        }

        const serialized = serializeFields(schema.value);

        if (serialized !== (props.modelValue ?? '')) {
            syncingFromParent = true;
            emit('update:modelValue', serialized);
            syncingFromParent = false;
        }
    },
    { deep: true },
);

defineExpose({
    applyExampleValues,
});
</script>

<template>
    <div v-if="schema.length" class="space-y-3">
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <label class="block text-sm font-medium">{{ t('Pre-fill search fields (optional)') }}</label>
            <button
                type="button"
                class="text-xs font-medium text-cyan-700 hover:underline"
                @click="applyExampleValues"
            >
                {{ t('Insert example') }}
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="grid grid-cols-[minmax(7rem,9rem)_1fr] gap-px border-b border-slate-200 bg-slate-100 text-xs font-medium uppercase tracking-wide text-slate-500">
                <div class="px-3 py-2">{{ t('Fixed key') }}</div>
                <div class="px-3 py-2">{{ t('Value') }}</div>
            </div>

            <div
                v-for="field in schema"
                :key="field.key"
                class="grid grid-cols-[minmax(7rem,9rem)_1fr] gap-px border-b border-slate-100 last:border-b-0"
            >
                <div class="flex items-center bg-slate-50 px-3 py-2.5">
                    <code class="rounded bg-slate-200/70 px-1.5 py-0.5 font-mono text-xs text-slate-700" :title="fieldLabel(field.key)">
                        {{ field.key }}
                    </code>
                </div>
                <div class="bg-white px-2 py-1.5">
                    <select
                        v-if="field.input === 'select'"
                        v-model="fields[field.key]"
                        class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm"
                    >
                        <option value="">{{ t('Leave empty') }}</option>
                        <option v-for="option in field.options" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                    <input
                        v-else
                        v-model="fields[field.key]"
                        :type="field.input"
                        :min="field.input === 'number' ? 1 : undefined"
                        class="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm"
                        :placeholder="String(field.example ?? '')"
                    >
                </div>
            </div>
        </div>

        <p class="text-xs leading-5 text-slate-500">
            {{ t('Keys are fixed for the mobile app. Edit values only. Leave empty to open search without pre-fill.') }}
        </p>
        <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
