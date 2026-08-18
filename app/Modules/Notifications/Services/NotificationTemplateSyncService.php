<?php

namespace App\Modules\Notifications\Services;

use App\Models\NotificationTemplate;
use App\Modules\Notifications\Support\NotificationLocales;
use App\Modules\Notifications\Support\NotificationTemplateCatalog;
use App\Modules\Notifications\Support\NotificationTemplateStaffLabels;

class NotificationTemplateSyncService
{
    /**
     * Ensure all catalog templates exist (does not overwrite edited customer copy).
     *
     * @return array{created: int, existing: int, translations_seeded: int, metadata_updated: int}
     */
    public function syncMissing(): array
    {
        $created = 0;
        $existing = 0;
        $translationsSeeded = 0;
        $metadataUpdated = 0;

        foreach (NotificationTemplateCatalog::definitions() as $definition) {
            $staffName = NotificationTemplateStaffLabels::english($definition['code']);
            $staffNameAr = NotificationTemplateStaffLabels::arabic($definition['code']);
            $translations = $this->withStaffArabicName($definition['translations'] ?? [], $staffNameAr);

            $template = NotificationTemplate::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $staffName,
                    'category' => $definition['category'] ?? 'general',
                    'description' => $definition['description'] ?? null,
                    'subject' => $definition['subject'] ?? null,
                    'body' => $definition['body'],
                    'translations' => $translations,
                    'channels' => $definition['channels'],
                    'variables' => $definition['variables'],
                    'version' => 1,
                    'is_active' => true,
                ],
            );

            if ($template->wasRecentlyCreated) {
                $created++;

                continue;
            }

            $existing++;
            $updates = [];

            if ($template->name !== $staffName) {
                $updates['name'] = $staffName;
            }

            if (empty($template->category) || $template->category === 'general') {
                $updates['category'] = $definition['category'] ?? $template->category;
            }

            if (! filled($template->description) && filled($definition['description'] ?? null)) {
                $updates['description'] = $definition['description'];
            }

            $currentTranslations = is_array($template->translations) ? $template->translations : [];

            if ($this->shouldSeedArabic($template, $definition)) {
                $currentTranslations = array_replace_recursive($currentTranslations, $definition['translations'] ?? []);
                $translationsSeeded++;
            }

            $merged = $this->withStaffArabicName($currentTranslations, $staffNameAr);

            if ($merged !== ($template->translations ?? [])) {
                $updates['translations'] = $merged;
            }

            if ($updates !== []) {
                $template->forceFill($updates)->save();
                $metadataUpdated++;
            }
        }

        return [
            'created' => $created,
            'existing' => $existing,
            'translations_seeded' => $translationsSeeded,
            'metadata_updated' => $metadataUpdated,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function shouldSeedArabic(NotificationTemplate $template, array $definition): bool
    {
        $catalogArabic = data_get($definition, 'translations.ar.body');

        if (! is_string($catalogArabic) || trim($catalogArabic) === '') {
            return false;
        }

        $existingArabic = data_get($template->translations, 'ar.body');

        return ! is_string($existingArabic) || trim($existingArabic) === '';
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    private function withStaffArabicName(array $translations, string $staffNameAr): array
    {
        $arabic = is_array($translations[NotificationLocales::AR] ?? null)
            ? $translations[NotificationLocales::AR]
            : [];
        $arabic['name'] = $staffNameAr;
        $translations[NotificationLocales::AR] = $arabic;

        return $translations;
    }
}
