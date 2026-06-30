<script setup>
import { useAdminLocale } from '../../composables/useAdminLocale';

defineProps({
    form: {
        type: Object,
        required: true,
    },
});

const { t } = useAdminLocale();

const fieldClass = 'mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-cyan-600';
const labelClass = 'text-sm font-medium text-slate-700';
const sectionClass = 'space-y-4 rounded-2xl border border-slate-100 bg-slate-50/70 p-5';
</script>

<template>
    <div class="space-y-8">
        <section :class="sectionClass">
            <h3 class="text-base font-semibold text-slate-900">{{ t('Airport codes') }}</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label :class="labelClass">{{ t('IATA code') }}</label>
                    <input v-model="form.iata_code" type="text" :class="fieldClass" maxlength="10">
                    <p v-if="form.errors.iata_code" class="mt-2 text-sm text-rose-600">{{ form.errors.iata_code }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('ICAO code') }}</label>
                    <input v-model="form.icao_code" type="text" :class="fieldClass" maxlength="10">
                    <p v-if="form.errors.icao_code" class="mt-2 text-sm text-rose-600">{{ form.errors.icao_code }}</p>
                </div>
            </div>
        </section>

        <section :class="sectionClass">
            <h3 class="text-base font-semibold text-slate-900">{{ t('Airport names') }}</h3>
            <div class="grid gap-4">
                <div>
                    <label :class="labelClass">{{ t('Name (English)') }}</label>
                    <input v-model="form.name_en" type="text" :class="fieldClass" required>
                    <p v-if="form.errors.name_en" class="mt-2 text-sm text-rose-600">{{ form.errors.name_en }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Name (Arabic)') }}</label>
                    <input v-model="form.name_ar" type="text" :class="fieldClass" dir="rtl">
                    <p v-if="form.errors.name_ar" class="mt-2 text-sm text-rose-600">{{ form.errors.name_ar }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Name (French)') }}</label>
                    <input v-model="form.name_fr" type="text" :class="fieldClass">
                    <p v-if="form.errors.name_fr" class="mt-2 text-sm text-rose-600">{{ form.errors.name_fr }}</p>
                </div>
            </div>
        </section>

        <section :class="sectionClass">
            <h3 class="text-base font-semibold text-slate-900">{{ t('Cities') }}</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label :class="labelClass">{{ t('City (English)') }}</label>
                    <input v-model="form.city_en" type="text" :class="fieldClass">
                    <p v-if="form.errors.city_en" class="mt-2 text-sm text-rose-600">{{ form.errors.city_en }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('City (Arabic)') }}</label>
                    <input v-model="form.city_ar" type="text" :class="fieldClass" dir="rtl">
                    <p v-if="form.errors.city_ar" class="mt-2 text-sm text-rose-600">{{ form.errors.city_ar }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('City (French)') }}</label>
                    <input v-model="form.city_fr" type="text" :class="fieldClass">
                    <p v-if="form.errors.city_fr" class="mt-2 text-sm text-rose-600">{{ form.errors.city_fr }}</p>
                </div>
            </div>
        </section>

        <section :class="sectionClass">
            <h3 class="text-base font-semibold text-slate-900">{{ t('Country') }}</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label :class="labelClass">{{ t('Country ISO2') }}</label>
                    <input v-model="form.country_iso2" type="text" :class="fieldClass" maxlength="2">
                    <p v-if="form.errors.country_iso2" class="mt-2 text-sm text-rose-600">{{ form.errors.country_iso2 }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Country name (English)') }}</label>
                    <input v-model="form.country_name_en" type="text" :class="fieldClass">
                    <p v-if="form.errors.country_name_en" class="mt-2 text-sm text-rose-600">{{ form.errors.country_name_en }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Country name (Arabic)') }}</label>
                    <input v-model="form.country_name_ar" type="text" :class="fieldClass" dir="rtl">
                    <p v-if="form.errors.country_name_ar" class="mt-2 text-sm text-rose-600">{{ form.errors.country_name_ar }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Country name (French)') }}</label>
                    <input v-model="form.country_name_fr" type="text" :class="fieldClass">
                    <p v-if="form.errors.country_name_fr" class="mt-2 text-sm text-rose-600">{{ form.errors.country_name_fr }}</p>
                </div>
            </div>
        </section>

        <section :class="sectionClass">
            <h3 class="text-base font-semibold text-slate-900">{{ t('Operations & metadata') }}</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label :class="labelClass">{{ t('Type') }}</label>
                    <input v-model="form.type" type="text" :class="fieldClass" :placeholder="t('e.g. large_airport, heliport')">
                    <p v-if="form.errors.type" class="mt-2 text-sm text-rose-600">{{ form.errors.type }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Scheduled service') }}</label>
                    <select v-model="form.scheduled_service" :class="fieldClass">
                        <option value="">{{ t('Not set') }}</option>
                        <option value="yes">{{ t('Yes') }}</option>
                        <option value="no">{{ t('No') }}</option>
                    </select>
                    <p v-if="form.errors.scheduled_service" class="mt-2 text-sm text-rose-600">{{ form.errors.scheduled_service }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Latitude') }}</label>
                    <input v-model="form.latitude_deg" type="number" step="any" :class="fieldClass">
                    <p v-if="form.errors.latitude_deg" class="mt-2 text-sm text-rose-600">{{ form.errors.latitude_deg }}</p>
                </div>
                <div>
                    <label :class="labelClass">{{ t('Longitude') }}</label>
                    <input v-model="form.longitude_deg" type="number" step="any" :class="fieldClass">
                    <p v-if="form.errors.longitude_deg" class="mt-2 text-sm text-rose-600">{{ form.errors.longitude_deg }}</p>
                </div>
                <div class="md:col-span-2">
                    <label :class="labelClass">{{ t('Translation status') }}</label>
                    <input v-model="form.translation_status" type="text" :class="fieldClass">
                    <p v-if="form.errors.translation_status" class="mt-2 text-sm text-rose-600">{{ form.errors.translation_status }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
