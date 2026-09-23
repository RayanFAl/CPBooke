<?php

namespace App\Modules\Api\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Api\Auth\Contracts\GoogleIdTokenVerifierInterface;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Api\User\Http\Requests\CancelAccountDeletionRequest;
use App\Modules\Api\User\Http\Requests\ConfirmOtpRequest;
use App\Modules\Api\User\Http\Requests\DeleteAccountRequest;
use App\Modules\Api\User\Http\Requests\EmailChangeRequest;
use App\Modules\Api\User\Http\Requests\EmailChangeVerifyRequest;
use App\Modules\Api\User\Http\Requests\PhoneChangeRequest;
use App\Modules\Api\User\Http\Requests\PhoneChangeVerifyRequest;
use App\Modules\Api\User\Http\Requests\UpdateProfileRequest;
use App\Modules\Api\User\Http\Requests\UploadAvatarRequest;
use App\Modules\Api\User\Services\CustomerAccountDeletionService;
use App\Modules\Api\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

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

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $mode = (string) $request->validated('mode');
        $payload = $this->userService->deleteAccount($request->user(), $mode);

        $message = 'Your account will be permanently deleted in '.CustomerAccountDeletionService::GRACE_PERIOD_DAYS.' days.';

        return ApiResponse::success($payload, $message);
    }

    public function cancelDeletion(CancelAccountDeletionRequest $request, GoogleIdTokenVerifierInterface $googleIdTokenVerifier): JsonResponse
    {
        $user = $this->resolveUserForCancellation($request, $googleIdTokenVerifier);
        $payload = $this->userService->cancelAccountDeletion($user);

        return ApiResponse::success(
            $payload,
            'Account deletion cancelled successfully. You can sign in again.',
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

    public function requestPhoneChange(PhoneChangeRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->userService->requestPhoneChange(
                $request->user(),
                $request->validated('phone'),
            ),
            'Verification code sent to the new phone number.',
        );
    }

    public function verifyPhoneChange(PhoneChangeVerifyRequest $request): JsonResponse
    {
        $user = $this->userService->confirmPhoneChange(
            $request->user(),
            $request->validated('phone'),
            $request->validated('otp'),
        );

        return ApiResponse::success(
            ['user' => UserResource::make($user)->resolve($request)],
            'Phone updated successfully.',
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

    private function resolveUserForCancellation(
        CancelAccountDeletionRequest $request,
        GoogleIdTokenVerifierInterface $googleIdTokenVerifier,
    ): User {
        $idToken = $request->validated('id_token');

        if (filled($idToken)) {
            $claims = $googleIdTokenVerifier->verify((string) $idToken);

            $user = User::query()
                ->where(function ($query) use ($claims): void {
                    $query->where('google_id', $claims['sub'])
                        ->orWhere('email', $claims['email']);
                })
                ->first();

            if ($user === null || ! $user->isCustomerAccount()) {
                throw ValidationException::withMessages([
                    'id_token' => ['No account scheduled for deletion was found for this Google account.'],
                ]);
            }

            return $user;
        }

        $login = (string) $request->validated('login');
        $password = (string) $request->validated('password');

        $user = User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if ($user === null || ! Hash::check($password, $user->password) || ! $user->isCustomerAccount()) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $user;
    }
}
