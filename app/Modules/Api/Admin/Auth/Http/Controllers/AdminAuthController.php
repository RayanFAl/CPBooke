<?php

namespace App\Modules\Api\Admin\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Auth\Http\Requests\LoginRequest;
use App\Modules\Api\Auth\Services\AuthService;
use App\Modules\Api\Resources\AuthUserResource;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthUserResource($this->authService->loginAdmin($request->toDto())),
            'Admin login completed successfully.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['user' => UserResource::make($this->authService->meAdmin($request->user()))->resolve($request)],
            'Authenticated admin fetched successfully.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logoutAdmin($request->user());

        return ApiResponse::success([], 'Admin logout completed successfully.');
    }
}