<?php

namespace App\Modules\Api\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Api\User\Http\Requests\ConfirmOtpRequest;
use App\Modules\Api\User\Http\Requests\EmailChangeRequest;
use App\Modules\Api\User\Http\Requests\EmailChangeVerifyRequest;
use App\Modules\Api\User\Http\Requests\UpdateProfileRequest;
use App\Modules\Api\User\Http\Requests\UploadAvatarRequest;
use App\Modules\Api\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['user' => UserResource::make($this->userService->profile($request->user()))->resolve($request)],
            'Profile fetched successfully.',
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->update($request->user(), $request->toDto());

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Profile updated successfully.',
        );
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->userService->uploadAvatar(
            $request->user(),
            $request->file('avatar'),
        );

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Avatar updated successfully.',
        );
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $this->userService->deleteAvatar($request->user());

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Avatar deleted successfully.',
        );
    }

    public function requestEmailChange(EmailChangeRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->userService->requestEmailChange(
                $request->user(),
                $request->validated('email'),
            ),
            'Verification code sent to the new email address.',
        );
    }

    public function verifyEmailChange(EmailChangeVerifyRequest $request): JsonResponse
    {
        $user = $this->userService->confirmEmailChange(
            $request->user(),
            $request->validated('email'),
            $request->validated('otp'),
        );

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Email updated successfully.',
        );
    }

    public function sendEmailVerification(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->userService->sendEmailVerification($request->user()),
            'Verification code sent to your email.',
        );
    }

    public function confirmEmailVerification(ConfirmOtpRequest $request): JsonResponse
    {
        $user = $this->userService->confirmEmailVerification(
            $request->user(),
            $request->validated('otp'),
        );

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Email verified successfully.',
        );
    }

    public function sendPhoneVerification(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->userService->sendPhoneVerification($request->user()),
            'Verification code sent to your phone.',
        );
    }

    public function confirmPhoneVerification(ConfirmOtpRequest $request): JsonResponse
    {
        $user = $this->userService->confirmPhoneVerification(
            $request->user(),
            $request->validated('otp'),
        );

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Phone verified successfully.',
        );
    }
}
