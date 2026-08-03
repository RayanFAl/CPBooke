<?php

namespace App\Modules\Support\Storage;

use App\Models\SupportMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportAttachmentStorage
{
    public const DISK = 'local';

    public const DIRECTORY = 'support/attachments';

    /**
     * @return array<string, int|string|null>
     */
    public function store(?UploadedFile $attachment): array
    {
        if ($attachment === null || ! $this->supportsMessageAttachments()) {
            return [];
        }

        $originalName = SupportAttachmentRules::sanitizeOriginalName(
            (string) $attachment->getClientOriginalName()
        );

        if (SupportAttachmentRules::isBlockedExtension($originalName)) {
            return [];
        }

        $extension = strtolower((string) $attachment->getClientOriginalExtension());
        $safeExtension = in_array($extension, SupportAttachmentRules::ALLOWED_EXTENSIONS, true)
            ? $extension
            : 'bin';

        $storedName = Str::uuid()->toString().'.'.$safeExtension;
        $path = $attachment->storeAs(self::DIRECTORY, $storedName, self::DISK);

        if (! is_string($path) || $path === '') {
            return [];
        }

        return [
            'attachment_path' => $path,
            'attachment_name' => $originalName,
            'attachment_mime' => $this->resolveMime($attachment),
            'attachment_size' => $attachment->getSize(),
        ];
    }

    public function temporaryUrl(SupportMessage $message): ?string
    {
        if ($message->attachment_path === null || $message->attachment_path === '') {
            return null;
        }

        $ttl = (int) config('support.attachments.signed_url_ttl_minutes', 30);

        return URL::temporarySignedRoute(
            'support.attachments.download',
            now()->addMinutes(max(1, $ttl)),
            ['message' => $message->id],
        );
    }

    public function exists(SupportMessage $message): bool
    {
        if ($message->attachment_path === null || $message->attachment_path === '') {
            return false;
        }

        return Storage::disk(self::DISK)->exists($message->attachment_path);
    }

    public function stream(SupportMessage $message): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        $path = (string) $message->attachment_path;
        $downloadName = SupportAttachmentRules::sanitizeOriginalName(
            (string) ($message->attachment_name ?: basename($path))
        );
        $mime = is_string($message->attachment_mime) && $message->attachment_mime !== ''
            ? $message->attachment_mime
            : 'application/octet-stream';

        return $disk->response(
            $path,
            $downloadName,
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            'inline',
        );
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
