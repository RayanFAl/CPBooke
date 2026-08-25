<script setup>
import { computed, useId } from 'vue';

const model = defineModel({
    type: [String, Number],
    default: '',
});

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    id: {
        type: String,
        default: '',
    },
    dir: {
        type: String,
        default: undefined,
    },
    placeholder: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    autocomplete: {
        type: String,
        default: undefined,
    },
});

const generatedId = useId();
const inputId = computed(() => props.id || generatedId);
const describedBy = computed(() => {
    if (props.error) {
        return `${inputId.value}-error`;
    }

    if (props.hint) {
        return `${inputId.value}-hint`;
    }

    return undefined;
});
</script>

<template>
    <label class="block space-y-2 text-sm font-medium text-slate-700">
        <span v-if="label">{{ label }}</span>
        <input
            :id="inputId"
            v-model="model"
            :type="type"
            :dir="dir"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :autocomplete="autocomplete"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-cyan-600 focus:ring-2 focus:ring-cyan-600/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
            :class="error ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' : ''"
        />
        <p
            v-if="error"
            :id="`${inputId}-error`"
            class="text-sm text-rose-600"
        >
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            :id="`${inputId}-hint`"
            class="text-sm text-slate-500"
        >
            {{ hint }}
        </p>
    </label>
</template>
