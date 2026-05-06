<script setup>
import AdminModulePage from '../../components/AdminModulePage.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
</script>

<template>
    <AdminLayout
        title="Dashboard"
        description="Top-level overview shell for Booke administration."
    >
        <AdminModulePage
            eyebrow="Overview"
            title="Dashboard"
            description="This page is intentionally lightweight and ready for KPI cards, activity feeds, and platform summaries in the next phase."
        >
            <p class="text-sm text-slate-600">
                Planned integration points: revenue KPIs, operational alerts, order funnel metrics, and support snapshots.
            </p>
        </AdminModulePage>
    </AdminLayout>
</template>