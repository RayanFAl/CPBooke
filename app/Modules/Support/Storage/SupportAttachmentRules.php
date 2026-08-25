<?php

namespace App\Modules\Support\Storage;

class SupportAttachmentRules
{
    /**
     * Allowed file extensions for support attachments.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif',
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
        'mp4', 'mov', 'm4v', '3gp', 'avi', 'webm',
    ];

    /**
     * Explicitly rejected extensions (executables / scripts).
     *
     * @var list<string>
     */
    public const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'ps1', 'vbs', 'js', 'jar',
        'php', 'phtml', 'phar', 'sh', 'bash', 'cgi', 'pl', 'py', 'rb',
        'dll', 'so', 'apk', 'dmg', 'iso', 'html', 'htm', 'svg',
    ];

    public static function maxKilobytes(): int
    {
        return (int) config('support.attachments.max_kilobytes', 20480);
    }

    /**
     * Laravel validation rules for an optional attachment field.
     *
     * @return list<string>
     */
    public static function fileRules(bool $requiredWithoutMessage = true): array
    {
        $rules = [
            'nullable',
            'file',
            'max:'.self::maxKilobytes(),
            'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            'extensions:'.implode(',', self::ALLOWED_EXTENSIONS),
        ];

        if ($requiredWithoutMessage) {
            $rules[] = 'required_without:message';
        }

        return $rules;
    }

    public static function sanitizeOriginalName(string $name): string
    {
        $normalized = str_replace(["\0", '\\'], '', $name);
        $basename = basename($normalized);
        $basename = preg_replace('/[^\p{L}\p{N}\.\-\_\(\) \[\]+]/u', '_', $basename) ?? 'attachment';
        $basename = trim($basename, '._ ');

        if ($basename === '' || $basename === '.' || $basename === '..') {
            return 'attachment';
        }

        return mb_substr($basename, 0, 180);
    }

    public static function isBlockedExtension(?string $filename): bool
    {
        if ($filename === null || $filename === '') {
            return false;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true);
    }
}
