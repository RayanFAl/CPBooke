<?php

namespace App\Modules\Admin\MobileApp\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MobileAppReleasePublished
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<User>  $users
     */
    public function __construct(
        public readonly string $version,
        public readonly int $versionCode,
        public readonly string $notesAr,
        public readonly string $notesEn,
        public readonly string $downloadUrl,
        public readonly string $pageUrl,
        public readonly bool $forceUpdate,
        public readonly array $users,
    ) {}
}
