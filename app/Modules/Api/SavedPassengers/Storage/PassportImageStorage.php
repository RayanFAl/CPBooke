<?php

namespace App\Modules\Api\SavedPassengers\Storage;

use App\Models\SavedPassenger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PassportImageStorage
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function diskName(): string
    {
        $disk = (string) config('saved-passengers.passport_image_disk', 'local');

        return $disk !== '' ? $disk : 'local';
    }

    public function store(SavedPassenger $passenger, UploadedFile $file): SavedPassenger
    {
        $this->assertAllowedImage($file);

        $disk = $this->diskName();
        $extension = $this->safeExtension($file);
        $directory = trim((string) config('saved-passengers.passport_image_directory', 'passport-images'), '/');
        $relativePath = sprintf(
            '%s/%s/%s/%s.%s',
            $directory,
            $passenger->user_id,
            $passenger->id,
            Str::lower((string) Str::ulid()),
            $extension,
        );

        $this->deleteStoredFile($passenger);

        $storedPath = $file->storeAs(
            dirname($relativePath),
            basename($relativePath),
            $disk,
        );

        if (! is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('Unable to store passport image.');
        }

        $passenger->forceFill([
            'passport_image_disk' => $disk,
            'passport_image_path' => $storedPath,
            'passport_image_mime' => $this->resolveMime($file),
            'passport_image_size' => $file->getSize() ?: null,
            'passport_image_uploaded_at' => now(),
        ])->save();

        return $passenger->refresh();
    }

    public function clear(SavedPassenger $passenger): SavedPassenger
    {
        $this->deleteStoredFile($passenger);

        $passenger->forceFill([
            'passport_image_disk' => null,
            'passport_image_path' => null,
            'passport_image_mime' => null,
            'passport_image_size' => null,
            'passport_image_uploaded_at' => null,
        ])->save();

        return $passenger->refresh();
    }

    public function deleteStoredFile(SavedPassenger $passenger): void
    {
        $path = $passenger->passport_image_path;
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = is_string($passenger->passport_image_disk) && $passenger->passport_image_disk !== ''
            ? $passenger->passport_image_disk
            : $this->diskName();

        Storage::disk($disk)->delete($path);
    }

    public function exists(SavedPassenger $passenger): bool
    {
        $path = $passenger->passport_image_path;
        if (! is_string($path) || $path === '') {
            return false;
        }

        $disk = is_string($passenger->passport_image_disk) && $passenger->passport_image_disk !== ''
            ? $passenger->passport_image_disk
            : $this->diskName();

        return Storage::disk($disk)->exists($path);
    }

    public function stream(SavedPassenger $passenger): StreamedResponse
    {
        abort_unless($this->exists($passenger), 404);

        $disk = is_string($passenger->passport_image_disk) && $passenger->passport_image_disk !== ''
            ? $passenger->passport_image_disk
            : $this->diskName();
        $path = (string) $passenger->passport_image_path;
        $mime = is_string($passenger->passport_image_mime) && $passenger->passport_image_mime !== ''
            ? $passenger->passport_image_mime
            : 'application/octet-stream';
        $downloadName = 'passport-'.$passenger->id.'.'.$this->extensionFromPath($path);

        return Storage::disk($disk)->response(
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

    public function assertAllowedImage(UploadedFile $file): void
    {
        $extension = $this->safeExtension($file);
        $mime = $this->resolveMime($file);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            abort(422, 'Passport image must be jpg, jpeg, png, or webp.');
        }

        if ($mime === null || ! in_array($mime, self::ALLOWED_MIMES, true)) {
            abort(422, 'Passport image MIME type is not allowed.');
        }
    }

    private function safeExtension(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: ''));

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return $extension;
    }

    private function resolveMime(UploadedFile $file): ?string
    {
        $detected = $file->getMimeType();
        if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream') {
            return strtolower($detected);
        }

        $client = $file->getClientMimeType();

        return is_string($client) && $client !== '' ? strtolower($client) : null;
    }

    private function extensionFromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true) ? $extension : 'jpg';
    }
}
