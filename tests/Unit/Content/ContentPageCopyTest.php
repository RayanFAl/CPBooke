<?php

namespace Tests\Unit\Content;

use App\Modules\Content\Support\ContentPageCopy;
use Tests\TestCase;

class ContentPageCopyTest extends TestCase
{
    public function test_privacy_copy_includes_account_deletion_retention_notice(): void
    {
        $english = ContentPageCopy::privacyEn();
        $arabic = ContentPageCopy::privacyAr();

        $this->assertStringContainsString(ContentPageCopy::ACCOUNT_DELETION_NOTICE_MARKER_EN, $english);
        $this->assertStringContainsString('<strong>one year</strong>', $english);
        $this->assertStringContainsString(ContentPageCopy::ACCOUNT_DELETION_NOTICE_MARKER_AR, $arabic);
        $this->assertStringContainsString('<strong>سنة واحدة</strong>', $arabic);
    }
}
