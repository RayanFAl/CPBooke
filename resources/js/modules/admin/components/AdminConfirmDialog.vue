<script setup>
import AdminButton from './AdminButton.vue';
import AdminModal from './AdminModal.vue';
import { useAdminConfirm } from '../composables/useAdminConfirm';
import { useAdminLocale } from '../composables/useAdminLocale';

const { confirmState, accept, cancel } = useAdminConfirm();
const { t } = useAdminLocale();
</script>

<template>
    <AdminModal
        :show="confirmState.show"
        :title="confirmState.title"
        max-width="md"
        @close="cancel"
    >
        <p class="text-sm leading-6 text-slate-600">
            {{ confirmState.message }}
        </p>

        <template #footer>
            <AdminButton variant="secondary" size="sm" @click="cancel">
                {{ t(confirmState.cancelLabel) }}
            </AdminButton>
            <AdminButton
                size="sm"
                :variant="confirmState.variant === 'danger' ? 'danger' : 'primary'"
                @click="accept"
            >
                {{ t(confirmState.confirmLabel) }}
            </AdminButton>
        </template>
    </AdminModal>
</template>
