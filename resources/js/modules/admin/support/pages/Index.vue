<script setup>
import AdminModulePage from '../../components/AdminModulePage.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
</script>

<template>
    <AdminLayout
        title="Support"
        description="Support shell for tickets, escalations, and customer issue management."
    >
        <AdminModulePage
            eyebrow="Care"
            title="Support"
            description="This placeholder is ready for ticket inboxes, SLA views, and conversation timelines once the support feature slice starts."
        >
            <p class="text-sm text-slate-600">
                Suitable future additions include tags, priority queues, canned actions, and assignment workflows.
            </p>
        </AdminModulePage>
    </AdminLayout>
</template>