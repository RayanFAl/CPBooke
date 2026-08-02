<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportAttachmentController extends Controller
{
    public function __construct(
        private readonly SupportAttachmentStorage $attachmentStorage,
    ) {}

    /**
     * Serve a support attachment via a temporary signed URL.
     *
     * The signature gates access for a short TTL. Files live on a private disk.
     */
    public function show(SupportMessage $message): StreamedResponse
    {
        if (! $this->attachmentStorage->hasStoredAttachment($message)) {
            abort(404);
        }

        $disposition = request()->query('disposition', 'inline');

        if ($disposition === 'attachment') {
            return $this->attachmentStorage->download($message);
        }

        return $this->attachmentStorage->inlineResponse($message);
    }
}
