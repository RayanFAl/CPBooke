<?php

namespace App\Modules\Support\Storage;

use App\Models\SupportMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportAttachmentStorage
{
    public const DISK = 'local';

    public const LEGACY_DISK = 'public';

    public const DIRECTORY = 'support/attachments';

    public const URL_TTL_MINUTES = 30;

    /**
     * @return array<string, int|string|null>
     */
    public function store(?UploadedFile $attachment): array
    {
        if ($attachment === null || ! $this->supportsMessageAttachments()) {
            return [];
        }

        $originalName = $this->sanitizeOriginalName($attachment->getClientOriginalName());

        return [
            'attachment_path' => $attachment->store(self::DIRECTORY, self::DISK),
            'attachment_name' => $originalName,
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

    public function temporaryUrl(SupportMessage $message): ?string
    {
        if (! $this->hasStoredAttachment($message)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'support.attachments.show',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['message' => $message->id],
        );
    }

    public function hasStoredAttachment(SupportMessage $message): bool
    {
        $path = $message->attachment_path;

        if (! is_string($path) || $path === '') {
            return false;
        }

        return $this->existsOnDisk(self::DISK, $path)
            || $this->existsOnDisk(self::LEGACY_DISK, $path);
    }

    public function download(SupportMessage $message): StreamedResponse
    {
        $path = (string) $message->attachment_path;
        $disk = $this->resolveDiskForPath($path);

        if ($disk === null) {
            abort(404);
        }

        $name = is_string($message->attachment_name) && $message->attachment_name !== ''
            ? $this->sanitizeOriginalName($message->attachment_name)
            : basename($path);

        $headers = [];
        if (is_string($message->attachment_mime) && $message->attachment_mime !== '') {
            $headers['Content-Type'] = $message->attachment_mime;
        }

        return Storage::disk($disk)->download($path, $name, $headers);
    }

    public function inlineResponse(SupportMessage $message): StreamedResponse
    {
        $path = (string) $message->attachment_path;
        $disk = $this->resolveDiskForPath($path);

        if ($disk === null) {
            abort(404);
        }

        $name = is_string($message->attachment_name) && $message->attachment_name !== ''
            ? $this->sanitizeOriginalName($message->attachment_name)
            : basename($path);

        $headers = [
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ];

        if (is_string($message->attachment_mime) && $message->attachment_mime !== '') {
            $headers['Content-Type'] = $message->attachment_mime;
        }

        return Storage::disk($disk)->response($path, $name, $headers);
    }

    public function resolveDiskForPath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if ($this->existsOnDisk(self::DISK, $path)) {
            return self::DISK;
        }

        if ($this->existsOnDisk(self::LEGACY_DISK, $path)) {
            return self::LEGACY_DISK;
        }

        return null;
    }

    private function existsOnDisk(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    private function sanitizeOriginalName(string $name): string
    {
        $basename = basename(str_replace(["\0", '\\'], '', $name));
        $basename = trim($basename);

        return $basename !== '' ? $basename : 'attachment';
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
