<?php

namespace App\Modules\Api\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Auth\Http\Requests\ChangePasswordRequest;
use App\Modules\Api\Auth\Http\Requests\ConfirmTwoFactorRequest;
use App\Modules\Api\Auth\Http\Requests\DisableTwoFactorRequest;
use App\Modules\Api\Auth\Http\Requests\GoogleAuthRequest;
use App\Modules\Api\Auth\Http\Requests\LoginRequest;
use App\Modules\Api\Auth\Http\Requests\RefreshTokenRequest;
use App\Modules\Api\Auth\Http\Requests\RegisterRequest;
use App\Modules\Api\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Api\Auth\Http\Requests\VerifyResetOtpRequest;
use App\Modules\Api\Auth\Http\Requests\VerifyTwoFactorRequest;
use App\Modules\Api\Auth\Services\AuthService;
use App\Modules\Api\Auth\Services\GoogleAuthService;
use App\Modules\Api\Auth\Services\PasswordResetService;
use App\Modules\Api\Auth\Services\TwoFactorService;
use App\Modules\Api\DTO\AuthResultDTO;
use App\Modules\Api\Resources\AuthUserResource;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly PasswordResetService $passwordResetService,
        private readonly TwoFactorService $twoFactorService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthUserResource($this->authService->register($request->toDto())),
            'Registration completed successfully.',
            [],
            201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->toDto(), $request->ip());

        if (is_array($result)) {
            return ApiResponse::success(
                $result,
                'Two-factor authentication required.',
            );
        }

        return ApiResponse::success(
            new AuthUserResource($result),
            'Login completed successfully.',
        );
    }

    public function google(GoogleAuthRequest $request, GoogleAuthService $googleAuthService): JsonResponse
    {
        return ApiResponse::success(
            new AuthUserResource(
                $googleAuthService->authenticate($request->toDto(), $request->ip()),
            ),
            'Login completed successfully.',
        );
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AuthUserResource($this->authService->refresh($request->validated('refresh_token'))),
            'Token refreshed successfully.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['user' => UserResource::make($this->authService->me($request->user()))->resolve($request)],
            'Authenticated user fetched successfully.',
        );
    }

    public function sessions(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['sessions' => $this->authService->sessions($request->user())],
            'Sessions fetched successfully.',
        );
    }

    public function destroySession(Request $request, int|string $session): JsonResponse
    {
        $this->authService->revokeSession($request->user(), $session);

        return ApiResponse::success(
            ['deleted' => true],
            'Session revoked successfully.',
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return ApiResponse::success([], 'Password changed successfully.');
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->twoFactorService->status($request->user()),
            'Two-factor status fetched successfully.',
        );
    }

    public function twoFactorEnable(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->twoFactorService->enable($request->user()),
            'Scan the QR / enter the secret, then confirm with a code.',
        );
    }

    public function twoFactorConfirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $this->twoFactorService->confirm($request->user(), $request->validated('code'));

        return ApiResponse::success(
            $this->twoFactorService->status($request->user()),
            'Two-factor authentication enabled successfully.',
        );
    }

    public function twoFactorDisable(DisableTwoFactorRequest $request): JsonResponse
    {
        $this->twoFactorService->disable(
            $request->user(),
            $request->validated('password'),
            $request->validated('code'),
        );

        return ApiResponse::success(
            $this->twoFactorService->status($request->user()),
            'Two-factor authentication disabled successfully.',
        );
    }

    public function twoFactorVerify(VerifyTwoFactorRequest $request): JsonResponse
    {
        /** @var AuthResultDTO $auth */
        $auth = $this->authService->verifyTwoFactor(
            $request->validated('temp_token'),
            $request->validated('code'),
            $request->ip(),
        );

        return ApiResponse::success(
            new AuthUserResource($auth),
            'Login completed successfully.',
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->passwordResetService->forgotPassword($request->validated('email')),
            'If an account exists for this email, a reset code has been sent.',
        );
    }

    public function verifyResetOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->passwordResetService->verifyResetOtp(
                $request->validated('email'),
                $request->validated('otp'),
            ),
            'OTP verified successfully.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->resetPassword(
            $request->validated('reset_token'),
            $request->validated('password'),
        );

        return ApiResponse::success([], 'Password reset successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success([], 'Logout completed successfully.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        return ApiResponse::success([], 'Logged out from all devices successfully.');
    }
}
