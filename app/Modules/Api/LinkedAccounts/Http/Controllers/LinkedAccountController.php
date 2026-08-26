<?php

namespace App\Modules\Api\LinkedAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LinkedAccount;
use App\Models\LinkedAccountRequest;
use App\Modules\Api\LinkedAccounts\Http\Requests\IndexLinkedAccountRequestsRequest;
use App\Modules\Api\LinkedAccounts\Http\Requests\RespondLinkedAccountRequestRequest;
use App\Modules\Api\LinkedAccounts\Http\Requests\SearchLinkedAccountUserRequest;
use App\Modules\Api\LinkedAccounts\Http\Requests\StoreLinkedAccountRequestRequest;
use App\Modules\Api\LinkedAccounts\Http\Requests\UpdateLinkedAccountPermissionsRequest;
use App\Modules\Api\LinkedAccounts\Services\LinkedAccountService;
use App\Modules\Api\Resources\LinkedAccountRequestResource;
use App\Modules\Api\Resources\LinkedAccountResource;
use App\Modules\Api\Resources\LinkedAccountSearchUserResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkedAccountController extends Controller
{
    public function __construct(
        private readonly LinkedAccountService $linkedAccountService,
    ) {
    }

    /**
     * List linked accounts for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LinkedAccount::class);

        $accounts = $this->linkedAccountService->listForUser($request->user());

        return ApiResponse::success(
            LinkedAccountResource::collection($accounts)->resolve($request),
            'Linked accounts fetched successfully.',
        );
    }

    /**
     * Send a link request to another user.
     */
    public function storeRequest(StoreLinkedAccountRequestRequest $request): JsonResponse
    {
        $this->authorize('create', LinkedAccount::class);

        $linkRequest = $this->linkedAccountService->createRequest(
            $request->user(),
            $request->string('to_user')->toString(),
            $request->string('relationship_type')->toString(),
            $request->filled('nickname') ? $request->string('nickname')->toString() : null,
            $request->filled('message') ? $request->string('message')->toString() : null,
        );

        return ApiResponse::success(
            LinkedAccountRequestResource::make($linkRequest)->resolve($request),
            'Link request sent successfully.',
            [],
            201,
        );
    }

    /**
     * List incoming link requests for the authenticated user.
     */
    public function indexRequests(IndexLinkedAccountRequestsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', LinkedAccountRequest::class);

        $status = $request->filled('status')
            ? $request->string('status')->toString()
            : null;

        $requests = $this->linkedAccountService->listRequestsForUser(
            $request->user(),
            $status,
        );

        return ApiResponse::success(
            LinkedAccountRequestResource::collection($requests)->resolve($request),
            'Link requests fetched successfully.',
        );
    }

    /**
     * Accept or reject a pending link request.
     */
    public function respond(
        RespondLinkedAccountRequestRequest $request,
        LinkedAccountRequest $linkedAccountRequest,
    ): JsonResponse {
        $this->authorize('respond', $linkedAccountRequest);

        $result = $this->linkedAccountService->respondToRequest(
            $linkedAccountRequest,
            $request->user(),
            $request->boolean('accept'),
        );

        $message = $request->boolean('accept')
            ? 'Link request accepted successfully.'
            : 'Link request rejected successfully.';

        return ApiResponse::success(
            LinkedAccountRequestResource::make($result['request'])->resolve($request),
            $message,
        );
    }

    /**
     * Remove a linked account (both sides).
     */
    public function destroy(LinkedAccount $linkedAccount): JsonResponse
    {
        $this->authorize('delete', $linkedAccount);

        $this->linkedAccountService->deleteLink($linkedAccount);

        return ApiResponse::success(
            [],
            'Linked account removed successfully.',
        );
    }

    /**
     * Update payment-request permissions for a linked account.
     */
    public function updatePermissions(
        UpdateLinkedAccountPermissionsRequest $request,
        LinkedAccount $linkedAccount,
    ): JsonResponse {
        $this->authorize('update', $linkedAccount);

        $account = $this->linkedAccountService->updatePermissions(
            $linkedAccount,
            $request->validated(),
        );

        return ApiResponse::success(
            LinkedAccountResource::make($account)->resolve($request),
            'Linked account permissions updated successfully.',
        );
    }

    /**
     * Search for a user that can be linked.
     */
    public function search(SearchLinkedAccountUserRequest $request): JsonResponse
    {
        $this->authorize('search', LinkedAccount::class);

        $user = $this->linkedAccountService->searchUser(
            $request->user(),
            $request->string('identifier')->toString(),
        );

        if ($user === null) {
            return ApiResponse::error(
                'No user was found for the provided identifier.',
                ['identifier' => ['No user was found for the provided identifier.']],
                'not_found',
                404,
            );
        }

        return ApiResponse::success(
            LinkedAccountSearchUserResource::make($user)->resolve($request),
            'User found successfully.',
        );
    }
}
