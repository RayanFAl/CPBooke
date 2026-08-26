<?php

namespace App\Modules\Api\LinkedAccounts\Services;

use App\Models\LinkedAccount;
use App\Models\LinkedAccountRequest;
use App\Models\User;
use App\Modules\Notifications\Events\PassengerActionDue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkedAccountService
{
    /**
     * List active linked accounts for the authenticated user.
     *
     * @return Collection<int, LinkedAccount>
     */
    public function listForUser(User $user): Collection
    {
        return LinkedAccount::query()
            ->with('linkedUser')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('created_at')
            ->get();
    }

    /**
     * List link requests received by the authenticated user.
     *
     * @return Collection<int, LinkedAccountRequest>
     */
    public function listRequestsForUser(User $user, ?string $status = null): Collection
    {
        return LinkedAccountRequest::query()
            ->with('fromUser')
            ->where('to_user_id', $user->id)
            ->when(
                $status !== null && $status !== '',
                fn ($query) => $query->where('status', $status),
            )
            ->latest('created_at')
            ->get();
    }

    /**
     * Create a pending link request.
     */
    public function createRequest(
        User $fromUser,
        string $toUserIdentifier,
        string $relationshipType,
        ?string $nickname = null,
        ?string $message = null,
    ): LinkedAccountRequest {
        $toUser = $this->resolveUserByIdentifier($toUserIdentifier);

        if ($toUser === null) {
            throw ValidationException::withMessages([
                'to_user' => ['No user was found for the provided identifier.'],
            ]);
        }

        if ((int) $toUser->id === (int) $fromUser->id) {
            throw ValidationException::withMessages([
                'to_user' => ['You cannot link your own account.'],
            ]);
        }

        if (! $toUser->isCustomerAccount() || ! $toUser->is_active) {
            throw ValidationException::withMessages([
                'to_user' => ['This user cannot be linked.'],
            ]);
        }

        if ($this->areLinked($fromUser->id, $toUser->id)) {
            throw ValidationException::withMessages([
                'to_user' => ['These accounts are already linked.'],
            ]);
        }

        if ($this->hasPendingRequestBetween($fromUser->id, $toUser->id)) {
            throw ValidationException::withMessages([
                'to_user' => ['A pending link request already exists for this user.'],
            ]);
        }

        $request = LinkedAccountRequest::query()->create([
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'relationship_type' => $relationshipType,
            'nickname' => $nickname,
            'message' => $message,
            'status' => LinkedAccountRequest::STATUS_PENDING,
        ]);

        $this->dispatchAfterCommit(function () use ($fromUser, $toUser, $request): void {
            event(new PassengerActionDue(
                $toUser,
                'LINK_REQUEST_RECEIVED',
                [
                    'sender_name' => $this->displayName($fromUser),
                    'deep_link' => '/linked-accounts',
                    'request_id' => $request->id,
                    'from_user_id' => (string) $fromUser->id,
                ],
                'linked_account_request',
                $request->id,
            ));
        });

        return $request;
    }

    /**
     * Accept or reject a pending link request.
     *
     * @return array{request: LinkedAccountRequest, accounts: Collection<int, LinkedAccount>|null}
     */
    public function respondToRequest(
        LinkedAccountRequest $request,
        User $actor,
        bool $accept,
    ): array {
        if ((int) $request->to_user_id !== (int) $actor->id) {
            abort(403, 'You are not allowed to respond to this link request.');
        }

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'accept' => ['This link request has already been responded to.'],
            ]);
        }

        $result = DB::transaction(function () use ($request, $accept): array {
            if (! $accept) {
                $request->fill([
                    'status' => LinkedAccountRequest::STATUS_REJECTED,
                    'responded_at' => now(),
                ])->save();

                return [
                    'request' => $request->refresh()->load('fromUser'),
                    'accounts' => null,
                ];
            }

            if ($this->areLinked($request->from_user_id, $request->to_user_id)) {
                throw ValidationException::withMessages([
                    'accept' => ['These accounts are already linked.'],
                ]);
            }

            $request->fill([
                'status' => LinkedAccountRequest::STATUS_ACCEPTED,
                'responded_at' => now(),
            ])->save();

            $fromAccount = LinkedAccount::query()->create([
                'user_id' => $request->from_user_id,
                'linked_user_id' => $request->to_user_id,
                'linked_account_request_id' => $request->id,
                'relationship_type' => $request->relationship_type,
                'nickname' => $request->nickname,
                'can_request_payment' => false,
                'can_receive_payment_requests' => true,
                'auto_approve' => false,
                'is_active' => true,
            ]);

            $toAccount = LinkedAccount::query()->create([
                'user_id' => $request->to_user_id,
                'linked_user_id' => $request->from_user_id,
                'linked_account_request_id' => $request->id,
                'relationship_type' => LinkedAccount::inverseRelationshipType($request->relationship_type),
                'nickname' => null,
                'can_request_payment' => false,
                'can_receive_payment_requests' => true,
                'auto_approve' => false,
                'is_active' => true,
            ]);

            return [
                'request' => $request->refresh()->load('fromUser'),
                'accounts' => new Collection([$fromAccount, $toAccount]),
            ];
        });

        $fromUser = $result['request']->fromUser;
        $code = $accept ? 'LINK_REQUEST_ACCEPTED' : 'LINK_REQUEST_REJECTED';

        if ($fromUser instanceof User) {
            $this->dispatchAfterCommit(function () use ($fromUser, $actor, $code, $result): void {
                event(new PassengerActionDue(
                    $fromUser,
                    $code,
                    [
                        'recipient_name' => $this->displayName($actor),
                        'deep_link' => '/linked-accounts',
                        'request_id' => $result['request']->id,
                    ],
                    'linked_account_request',
                    $result['request']->id,
                ));
            });
        }

        return $result;
    }

    /**
     * Soft-unlink by removing both sides of the relationship.
     */
    public function deleteLink(LinkedAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            LinkedAccount::query()
                ->where(function ($query) use ($account): void {
                    $query->where('user_id', $account->user_id)
                        ->where('linked_user_id', $account->linked_user_id);
                })
                ->orWhere(function ($query) use ($account): void {
                    $query->where('user_id', $account->linked_user_id)
                        ->where('linked_user_id', $account->user_id);
                })
                ->delete();
        });
    }

    /**
     * Update payment-request permissions for a linked account.
     *
     * @param  array{can_request_payment?: bool, can_receive_payment_requests?: bool, auto_approve?: bool}  $permissions
     */
    public function updatePermissions(LinkedAccount $account, array $permissions): LinkedAccount
    {
        $account->fill([
            'can_request_payment' => array_key_exists('can_request_payment', $permissions)
                ? (bool) $permissions['can_request_payment']
                : $account->can_request_payment,
            'can_receive_payment_requests' => array_key_exists('can_receive_payment_requests', $permissions)
                ? (bool) $permissions['can_receive_payment_requests']
                : $account->can_receive_payment_requests,
            'auto_approve' => array_key_exists('auto_approve', $permissions)
                ? (bool) $permissions['auto_approve']
                : $account->auto_approve,
        ])->save();

        return $account->refresh()->load('linkedUser');
    }

    /**
     * Search a customer by phone, email, or user id.
     */
    public function searchUser(User $actor, string $identifier): ?User
    {
        $user = $this->resolveUserByIdentifier($identifier);

        if ($user === null) {
            return null;
        }

        if ((int) $user->id === (int) $actor->id) {
            throw ValidationException::withMessages([
                'identifier' => ['You cannot search for your own account.'],
            ]);
        }

        if (! $user->isCustomerAccount() || ! $user->is_active) {
            return null;
        }

        return $user;
    }

    public function resolveUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        return User::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('email', $identifier)
                    ->orWhere('phone', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->first();
    }

    public function areLinked(int $userId, int $otherUserId): bool
    {
        return LinkedAccount::query()
            ->where('user_id', $userId)
            ->where('linked_user_id', $otherUserId)
            ->where('is_active', true)
            ->exists();
    }

    public function hasPendingRequestBetween(int $userId, int $otherUserId): bool
    {
        return LinkedAccountRequest::query()
            ->where('status', LinkedAccountRequest::STATUS_PENDING)
            ->where(function ($query) use ($userId, $otherUserId): void {
                $query->where(function ($inner) use ($userId, $otherUserId): void {
                    $inner->where('from_user_id', $userId)
                        ->where('to_user_id', $otherUserId);
                })->orWhere(function ($inner) use ($userId, $otherUserId): void {
                    $inner->where('from_user_id', $otherUserId)
                        ->where('to_user_id', $userId);
                });
            })
            ->exists();
    }

    private function displayName(User $user): string
    {
        return $user->full_name ?: $user->name ?: 'Customer';
    }

    private function dispatchAfterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
