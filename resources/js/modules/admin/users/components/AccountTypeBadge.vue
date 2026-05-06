<script setup>
const props = defineProps({
    accountType: {
        type: String,
        required: true,
    },
});

const paletteByType = {
    admin: 'bg-cyan-100 text-cyan-800',
    customer: 'bg-emerald-100 text-emerald-800',
};

const labelByType = {
    admin: 'Admin',
    customer: 'Customer',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
        :class="paletteByType[accountType] ?? 'bg-slate-100 text-slate-700'"
    >
        {{ labelByType[accountType] ?? accountType }}
    </span>
</template>