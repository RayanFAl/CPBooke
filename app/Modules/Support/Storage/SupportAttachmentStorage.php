<?php

namespace App\Modules\Support\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

class SupportAttachmentStorage
{
    /**
     * @return array<string, int|string|null>
     */
    public function store(?UploadedFile $attachment): array
    {
        if ($attachment === null || ! $this->supportsMessageAttachments()) {
            return [];
        }

        return [
            'attachment_path' => $attachment->store('support/attachments', 'public'),
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_mime' => $this->resolveMime($attachment),
            'attachment_size' => $attachment->getSize(),
        ];
    }

    public function supportsMessageAttachments(): bool
    {
        if (! Schema::hasTable('support_messages')) {
            return false;
        }

        foreach (['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size'] as $column) {
            if (! Schema::hasColumn('support_messages', $column)) {
                return false;
            }
        }

        return true;
    }

    private function resolveMime(UploadedFile $attachment): ?string
    {
        $detectedMime = $attachment->getMimeType();

        if (is_string($detectedMime) && $detectedMime !== '' && $detectedMime !== 'application/octet-stream') {
            return $detectedMime;
        }

        $clientMime = $attachment->getClientMimeType();

        return is_string($clientMime) && $clientMime !== '' ? $clientMime : null;
    }
}
