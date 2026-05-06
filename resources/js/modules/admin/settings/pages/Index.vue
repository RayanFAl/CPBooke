<script setup>
import AdminModulePage from '../../components/AdminModulePage.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
</script>

<template>
    <AdminLayout
        title="Settings"
        description="Configuration shell for platform-level settings, preferences, and operational toggles."
    >
        <AdminModulePage
            eyebrow="Configuration"
            title="Settings"
            description="This module will host system settings, access preferences, and environment-aware configuration screens in later phases."
        >
            <p class="text-sm text-slate-600">
                A clean split between configuration categories can be added here without changing the route and layout contract.
            </p>
        </AdminModulePage>
    </AdminLayout>
</template>