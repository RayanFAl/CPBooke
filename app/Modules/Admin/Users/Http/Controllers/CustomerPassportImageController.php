<?php

namespace App\Modules\Admin\Users\Http\Controllers;

use App\Models\SavedPassenger;
use App\Models\User;
use App\Modules\Admin\Access\Services\AccessControlService;
use App\Modules\Api\SavedPassengers\Storage\PassportImageStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPassportImageController
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
        private readonly PassportImageStorage $passportImageStorage,
    ) {}

    /**
     * Stream a private passport scan for staff (no public URL).
     */
    public function show(User $user, SavedPassenger $savedPassenger): StreamedResponse
    {
        abort_unless($user->isCustomerAccount(), 404);
        abort_unless((int) $savedPassenger->user_id === (int) $user->id, 404);

        $this->accessControlService->assertCanManageUser(request()->user(), $user);

        return $this->passportImageStorage->stream($savedPassenger);
    }
}
