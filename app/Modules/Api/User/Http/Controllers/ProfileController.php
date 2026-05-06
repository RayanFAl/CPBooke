<?php

namespace App\Modules\Api\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Api\User\Http\Requests\UpdateProfileRequest;
use App\Modules\Api\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    /**
     * Display the authenticated API user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['user' => UserResource::make($this->userService->profile($request->user()))->resolve($request)],
            'Profile fetched successfully.',
        );
    }

    /**
     * Update the authenticated API user's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->update($request->user(), $request->toDto());

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Profile updated successfully.',
        );
    }
}