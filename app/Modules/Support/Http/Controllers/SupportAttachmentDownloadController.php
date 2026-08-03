<?php

namespace App\Modules\Support\Http\Controllers;

use App\Models\SupportMessage;
use App\Modules\Support\Services\SupportAttachmentAccessService;
use App\Modules\Support\Storage\SupportAttachmentStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupportAttachmentDownloadController
{
    public function __construct(
        private readonly SupportAttachmentStorage $storage,
        private readonly SupportAttachmentAccessService $access,
    ) {
    }

    public function __invoke(Request $request, SupportMessage $message): StreamedResponse
    {
        if ($message->attachment_path === null || $message->attachment_path === '') {
            throw new NotFoundHttpException('Attachment not found.');
        }

        // Signed URLs are the primary gate. When an authenticated principal is present,
        // re-check ownership / RBAC so a leaked link cannot be reused by another session.
        $user = $request->user();

        if ($user !== null && ! $this->access->canAccess($user, $message)) {
            throw new AccessDeniedHttpException('You are not allowed to access this attachment.');
        }

        if (! $this->storage->exists($message)) {
            throw new NotFoundHttpException('Attachment file is missing.');
        }

        return $this->storage->stream($message);
    }
}
