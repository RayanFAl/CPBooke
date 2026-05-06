<?php

namespace App\Modules\Api\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Auth\Http\Requests\LoginRequest;
use App\Modules\Api\Auth\Http\Requests\RegisterRequest;
use App\Modules\Api\Resources\AuthUserResource;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Auth\Services\AuthService;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Register a new API user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthUserResource($this->authService->register($request->toDto())),
            'Registration completed successfully.',
            [],
            201,
        );
    }

    /**
     * Authenticate an API user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthUserResource($this->authService->login($request->toDto())),
            'Login completed successfully.',
        );
    }

    /**
     * Return the authenticated API user.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['user' => UserResource::make($this->authService->me($request->user()))->resolve($request)],
            'Authenticated user fetched successfully.',
        );
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success([], 'Logout completed successfully.');
    }
}