<?php

use App\Models\ContentPage;
use App\Modules\Content\Support\ContentPageCatalog;
use App\Modules\Content\Support\ContentPageCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure privacy-policy includes the account-deletion retention notice (AR + EN).
     */
    public function up(): void
    {
        if (! Schema::hasTable('content_pages')) {
            return;
        }

        $page = ContentPage::query()
            ->where('slug', ContentPageCatalog::SLUG_PRIVACY_POLICY)
            ->first();

        if ($page === null) {
            return;
        }

        $bodyEn = (string) $page->body_en;
        $bodyAr = (string) $page->body_ar;

        if (! str_contains($bodyEn, ContentPageCopy::ACCOUNT_DELETION_NOTICE_MARKER_EN)) {
            $bodyEn = rtrim($bodyEn)."\n".ContentPageCopy::accountDeletionSectionEn(false);
        }

        if (! str_contains($bodyAr, ContentPageCopy::ACCOUNT_DELETION_NOTICE_MARKER_AR)) {
            $bodyAr = rtrim($bodyAr)."\n".ContentPageCopy::accountDeletionSectionAr(false);
        }

        $page->forceFill([
            'body_en' => $bodyEn,
            'body_ar' => $bodyAr,
        ])->save();
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_pages')) {
            return;
        }

        $page = ContentPage::query()
            ->where('slug', ContentPageCatalog::SLUG_PRIVACY_POLICY)
            ->first();

        if ($page === null) {
            return;
        }

        $page->forceFill([
            'body_en' => $this->stripAccountDeletionSection((string) $page->body_en, ContentPageCopy::ACCOUNT_DELETION_NOTICE_MARKER_EN),
            'body_ar' => $this->stripAccountDeletionSection((string) $page->body_ar, ContentPageCopy::ACCOUNT_DELETION_NOTICE_MARKER_AR),
        ])->save();
    }

    private function stripAccountDeletionSection(string $body, string $marker): string
    {
        if (! str_contains($body, $marker)) {
            return $body;
        }

        $stripped = preg_replace(
            '/\s*<h2>[^<]*<\/h2>\s*<p>[^<]*'.preg_quote($marker, '/').'.*?<\/p>/su',
            '',
            $body,
        );

        return is_string($stripped) ? rtrim($stripped) : $body;
    }
};
