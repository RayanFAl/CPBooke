<script setup>
import AdminModulePage from '../../components/AdminModulePage.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
</script>

<template>
    <AdminLayout
        title="Finance"
        description="Financial oversight shell for payouts, ledgers, settlements, and reporting."
    >
        <AdminModulePage
            eyebrow="Accounting"
            title="Finance"
            description="This area is reserved for revenue summaries, payout queues, and accounting workflows that remain isolated from UI concerns elsewhere."
        >
            <p class="text-sm text-slate-600">
                Next implementation can separate reports, invoices, and payout pipelines into submodules without changing this entry point.
            </p>
        </AdminModulePage>
    </AdminLayout>
</template>