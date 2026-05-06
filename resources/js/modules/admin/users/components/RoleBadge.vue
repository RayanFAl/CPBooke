<script setup>
const props = defineProps({
    role: {
        type: Object,
        default: null,
    },
});

const paletteByRole = {
    super_admin: 'bg-rose-100 text-rose-800',
    admin: 'bg-cyan-100 text-cyan-800',
    team_member: 'bg-amber-100 text-amber-800',
    none: 'bg-slate-100 text-slate-700',
};

const roleKey = props.role?.name ?? 'none';
const roleLabel = props.role?.label ?? 'No role';
</script>

<template>
    <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em]"
        :class="paletteByRole[roleKey] ?? paletteByRole.none"
    >
        {{ roleLabel }}
    </span>
</template>