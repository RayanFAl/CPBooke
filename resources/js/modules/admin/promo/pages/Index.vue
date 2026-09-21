<script setup>
import AdminLayout from '../../layouts/AdminLayout.vue';
import LoyaltyResultsPromoPanel from '../../loyalty/components/LoyaltyResultsPromoPanel.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useAdminLocale } from '../../composables/useAdminLocale';

const props = defineProps({
    settings: {
        type: Object,
        default: null,
    },
    settings_update_url: {
        type: String,
        default: '',
    },
    can_manage_settings: {
        type: Boolean,
        default: false,
    },
});

const { t } = useAdminLocale();
const page = usePage();
const settingsState = ref(props.settings ?? null);

const permissions = computed(() => page.props.auth?.user?.permissions ?? []);
const canManageSettings = computed(
    () => props.can_manage_settings || permissions.value.includes('loyalty.settings.manage'),
);

const onPromoSaved = (data) => {
    if (data) {
        settingsState.value = data;
    }
};
</script>

<template>
    <AdminLayout>
        <Head :title="t('Promo card')" />

        <LoyaltyResultsPromoPanel
            :initial-settings="settingsState"
            :can-manage="canManageSettings"
            :update-url="settings_update_url"
            @saved="onPromoSaved"
        />
    </AdminLayout>
</template>
