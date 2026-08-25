<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_type',
    'visitor_hash',
    'platform',
    'version',
    'apk_filename',
    'locale',
])]
class AppDownloadEvent extends Model
{
    public const TYPE_PAGE_VIEW = 'page_view';

    public const TYPE_APK_DOWNLOAD = 'apk_download';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
