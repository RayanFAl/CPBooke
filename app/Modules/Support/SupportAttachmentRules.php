<?php

namespace App\Modules\Support;

class SupportAttachmentRules
{
    public const MAX_KILOBYTES = 20480;

    /**
     * Allowed extensions for support message attachments.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
        'mp4', 'mov', 'm4v', '3gp', 'avi', 'webm',
    ];

    /**
     * @return list<string>
     */
    public static function attachmentFieldRules(bool $requiredWithoutMessage = true): array
    {
        $rules = [
            'nullable',
            'file',
            'max:'.self::MAX_KILOBYTES,
            'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
        ];

        if ($requiredWithoutMessage) {
            $rules[] = 'required_without:message';
        }

        return $rules;
    }
}
