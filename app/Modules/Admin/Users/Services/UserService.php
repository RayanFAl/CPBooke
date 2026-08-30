<?php

namespace App\Modules\Admin\Users\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\Access\Services\AccessControlService;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Loyalty\Services\LoyaltyService as CustomerLoyaltyService;
use App\Support\Rbac\RbacAuditLogger;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
        private readonly CustomerLoyaltyService $loyaltyService,
        private readonly CustomerCrmActivityService $crmActivityService,
        private readonly AuditRecorder $auditRecorder,
        private readonly RbacAuditLogger $rbacAuditLogger,
    ) {}

    /**
     * Build the paginated admin users listing.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(User $actor, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->select([
                'id',
                'name',
                'full_name',
                'email',
                'phone',
                'country',
                'account_type',
                'is_admin',
                'is_active',
                'last_login_at',
                'created_at',
            ])
            ->when(! $actor->hasRole(RbacRegistry::ROLE_SUPER_ADMIN), function ($query): void {
                $query->whereDoesntHave('roles', function ($roleQuery): void {
                    $roleQuery->where('name', RbacRegistry::ROLE_SUPER_ADMIN);
                });
            })
            ->when($filters['name'] ?? null, function ($query, $name): void {
                $query->where(function ($nestedQuery) use ($name): void {
                    $nestedQuery
                        ->where('full_name', 'like', "%{$name}%")
                        ->orWhere('name', 'like', "%{$name}%");
                });
            })
            ->when($filters['email'] ?? null, fn ($query, $email) => $query->where('email', 'like', "%{$email}%"))
            ->when($filters['phone'] ?? null, fn ($query, $phone) => $query->where('phone', 'like', "%{$phone}%"))
            ->when($filters['account_type'] ?? null, fn ($query, $accountType) => $query->where('account_type', $accountType))
            ->when($filters['role'] ?? null, function ($query, $role): void {
                if ($role === 'unassigned') {
                    $query->whereDoesntHave('roles');

                    return;
                }

                $query->whereHas('roles', function ($roleQuery) use ($role): void {
                    $roleQuery->where('name', $role);
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status): void {
                $query->where('is_active', $status === 'active');
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => $this->summaryPayload($user));
    }

    /**
     * Build the detailed payload for a user profile.
     *
     * @return array<string, mixed>
     */
    public function detailPayload(User $user, ?User $actor = null): array
    {
        $user->loadMissing('roles.permissions');

        $orderSelect = [
            'id',
            'booking_reference',
            'status',
            'currency',
            'total_amount',
            'created_at',
        ];

        if (Schema::hasColumn('orders', 'service_type')) {
            $orderSelect[] = 'service_type';
        }

        if (Schema::hasColumn('orders', 'payment_status')) {
            $orderSelect[] = 'payment_status';
        }

        $recentOrders = Order::query()
            ->select($orderSelect)
            ->whereBelongsTo($user, 'customer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'booking_reference' => $order->booking_reference,
                'service_type' => $order->getAttribute('service_type'),
                'status' => $order->status,
                'payment_status' => $order->getAttribute('payment_status'),
                'amount' => number_format((float) $order->total_amount, 2, '.', ''),
                'currency' => $order->currency,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentFinancialTransactions = Schema::hasTable('financial_transactions')
            ? FinancialTransaction::query()
                ->select([
                    'id',
                    'order_id',
                    'type',
                    'amount',
                    'currency',
                    'source',
                    'created_at',
                ])
                ->whereHas('order', fn ($query) => $query->whereBelongsTo($user, 'customer'))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (FinancialTransaction $transaction): array => [
                    'id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                    'type' => $transaction->type,
                    'amount' => number_format((float) $transaction->amount, 2, '.', ''),
                    'currency' => $transaction->currency,
                    'source' => $transaction->source,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        $walletBalance = null;
        $walletCurrency = null;

        if (Schema::hasTable('financial_transactions')) {
            $transactionRows = FinancialTransaction::query()
                ->select(['type', 'amount', 'currency'])
                ->whereHas('order', fn ($query) => $query->whereBelongsTo($user, 'customer'))
                ->get();

            if ($transactionRows->isNotEmpty()) {
                $walletBalance = $transactionRows->reduce(function (float $carry, FinancialTransaction $transaction): float {
                    $amount = (float) $transaction->amount;

                    return $carry + match ($transaction->type) {
                        FinancialTransaction::TYPE_PAYMENT => $amount,
                        FinancialTransaction::TYPE_REFUND, FinancialTransaction::TYPE_PAYOUT => -$amount,
                        default => 0,
                    };
                }, 0.0);

                $walletCurrency = $transactionRows->pluck('currency')->filter()->first();
            }
        }

        $recentActivities = Schema::hasTable('order_histories')
            ? OrderHistory::query()
                ->with(['order:id,booking_reference'])
                ->select([
                    'id',
                    'order_id',
                    'user_id',
                    'action',
                    'field',
                    'old_value',
                    'new_value',
                    'created_at',
                ])
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (OrderHistory $activity): array => [
                    'id' => $activity->id,
                    'order_id' => $activity->order_id,
                    'booking_reference' => $activity->order?->booking_reference,
                    'action' => $activity->action,
                    'field' => $activity->field,
                    'old_value' => $activity->old_value,
                    'new_value' => $activity->new_value,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        $support = $actor ? $this->supportPayload($user, $actor) : [
            'active_ticket_count' => 0,
            'active_ticket' => null,
        ];

        return [
            'id' => $user->id,
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'account_type' => $user->account_type,
            'is_active' => (bool) $user->is_active,
            'is_admin' => (bool) $user->is_admin,
            'role' => $this->rolePayload($user),
            'permissions' => $user->permissionNames(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'preferred_locale' => $user->preferred_locale,
            'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'crm' => $this->crmActivityService->payload($user),
            'recent_orders' => $recentOrders,
            'financial_summary' => [
                'wallet_balance' => $walletBalance !== null ? number_format($walletBalance, 2, '.', '') : null,
                'currency' => $walletCurrency,
                'has_wallet_data' => Schema::hasTable('financial_transactions'),
            ],
            'financial_transactions' => $recentFinancialTransactions,
            'recent_activities' => $recentActivities,
            'loyalty' => $this->loyaltyService->profilePayload($user, false),
            'support' => $support,
        ];
    }

    /**
     * Build the edit payload for a user profile.
     *
     * @return array<string, mixed>
     */
    public function editPayload(User $user): array
    {
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'account_type' => $user->account_type,
            'is_active' => (bool) $user->is_active,
            'is_admin' => (bool) $user->is_admin,
            'role' => $user->primaryRole()?->name,
            'permissions' => $this->formPermissionNames($user),
        ];
    }

    /**
     * Create a new administrative user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        $roleName = (string) $data['role'];
        $permissionNames = $this->normalizePermissionNames($roleName, $data['permissions'] ?? []);

        $this->accessControlService->assertCanAssignRole($actor, $roleName);
        $this->accessControlService->assertCanAssignPermissions($actor, $permissionNames, $roleName);

        return DB::transaction(function () use ($data, $roleName, $permissionNames): User {
            $user = User::query()->create([
                'name' => $data['full_name'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'country' => $data['country'] ?: null,
                'password' => $data['password'],
                'is_admin' => true,
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'is_active' => true,
            ]);

            $user->syncRolesByName([$roleName]);
            $this->syncUserPermissions($user, $roleName, $permissionNames);

            return $user->load(['roles', 'permissions']);
        });
    }

    /**
     * Update the specified user.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $user, array $data): User
    {
        $this->accessControlService->assertCanManageUser($actor, $user);

        $currentRoleName = $user->primaryRole()?->name;
        $nextRoleName = (string) $data['role'];
        $permissionNames = $this->normalizePermissionNames($nextRoleName, $data['permissions'] ?? []);

        $this->accessControlService->assertCanAssignRole($actor, $nextRoleName, $user);
        $this->accessControlService->assertCanAssignPermissions($actor, $permissionNames, $nextRoleName, $user);

        if ($user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)
            && $currentRoleName !== $nextRoleName
            && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => 'The last active super admin must keep the super admin role.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $nextRoleName, $permissionNames): User {
            $user->fill([
                'name' => $data['full_name'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'country' => $data['country'] ?: null,
            ])->save();

            $user->syncRolesByName([$nextRoleName]);
            $this->syncUserPermissions($user, $nextRoleName, $permissionNames);

            return $user->refresh()->load(['roles', 'permissions']);
        });
    }

    /**
     * Update customer identity fields without touching roles or permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateIdentity(User $actor, User $user, array $data): User
    {
        if (! $user->isCustomerAccount()) {
            throw ValidationException::withMessages([
                'account_type' => 'Only customer accounts can be updated from the CRM identity form.',
            ]);
        }

        $this->accessControlService->assertCanManageUser($actor, $user);

        $oldValues = [
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
        ];

        $user->fill([
            'name' => $data['full_name'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'country' => $data['country'] ?: null,
        ])->save();

        $newValues = [
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
        ];

        $this->auditRecorder->record(
            module: 'users',
            action: 'customer.identity.updated',
            subject: 'Updated customer identity from CRM.',
            entityType: 'user',
            entityId: $user->id,
            actor: $actor,
            oldValues: $oldValues,
            newValues: $newValues,
        );

        $this->rbacAuditLogger->log(
            'customer.identity.updated',
            'users.update',
            $actor,
            'user',
            $user->id,
            ['changed' => array_keys(array_diff_assoc($newValues, $oldValues))],
        );

        return $user->refresh();
    }

    /**
     * Toggle the specified user's active status.
     *
     * @return array{updated: bool, message: string}
     */
    public function toggleStatus(User $actor, User $user): array
    {
        $this->accessControlService->assertCanManageUser($actor, $user);

        if ($user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN) && $user->is_active && $this->isLastActiveSuperAdmin($user)) {
            return [
                'updated' => false,
                'message' => 'The last active super admin cannot be deactivated.',
            ];
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
        ])->save();

        return [
            'updated' => true,
            'message' => $user->is_active
                ? 'User account activated successfully.'
                : 'User account deactivated successfully.',
        ];
    }

    /**
     * Permanently delete a Control Panel team member.
     *
     * @return array{deleted: bool, message: string}
     */
    public function deleteTeamMember(User $actor, User $user): array
    {
        if (! $user->isAdminAccount()) {
            return [
                'deleted' => false,
                'message' => 'Only team accounts can be deleted from the Control Panel.',
            ];
        }

        if ($actor->is($user)) {
            return [
                'deleted' => false,
                'message' => 'You cannot delete your own account.',
            ];
        }

        $this->accessControlService->assertCanManageUser($actor, $user);

        if ($user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN) && $this->isLastActiveSuperAdmin($user)) {
            return [
                'deleted' => false,
                'message' => 'The last active super admin cannot be deleted.',
            ];
        }

        $snapshot = [
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'role' => $user->primaryRole()?->name,
        ];

        DB::transaction(function () use ($actor, $user, $snapshot): void {
            $this->rbacAuditLogger->log(
                'team_member.deleted',
                'users.update',
                $actor,
                'user',
                $user->id,
                $snapshot,
            );

            $user->delete();
        });

        return [
            'deleted' => true,
            'message' => 'Team member deleted successfully.',
        ];
    }

    /**
     * Build user-profile placeholder datasets until related modules exist.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public function profilePlaceholders(): array
    {
        return [
            'orders' => [
                ['title' => 'Recent orders feed', 'description' => 'Order history integration will be attached from the Orders module.'],
                ['title' => 'Lifecycle timeline', 'description' => 'Booking status changes and service milestones will appear here.'],
            ],
            'payments' => [
                ['title' => 'Payment activity', 'description' => 'Payment records, refunds, and balance summaries are reserved for Finance integration.'],
                ['title' => 'Risk indicators', 'description' => 'Chargeback and reconciliation indicators can be injected here later.'],
            ],
            'tickets' => [
                ['title' => 'Support threads', 'description' => 'Open and resolved support interactions will appear here once the Support module is connected.'],
                ['title' => 'Escalation context', 'description' => 'Priority, ownership, and SLA-related data will be surfaced in this section.'],
            ],
        ];
    }

    /**
     * Build the summary payload for table listings.
     *
     * @return array<string, mixed>
     */
    private function summaryPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name ?: $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'account_type' => $user->account_type,
            'is_admin' => (bool) $user->is_admin,
            'is_active' => (bool) $user->is_active,
            'role' => $this->rolePayload($user),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * Build the support snapshot for the user profile.
     *
     * @return array{active_ticket_count:int, active_ticket:array<string, mixed>|null}
     */
    private function supportPayload(User $user, User $actor): array
    {
        if (! in_array('support.view', $actor->permissionNames(), true)
            || ! Schema::hasTable('support_tickets')
            || ! Schema::hasTable('support_messages')) {
            return [
                'active_ticket_count' => 0,
                'active_ticket' => null,
            ];
        }

        $activeTickets = SupportTicket::query()
            ->with([
                'assignee:id,name,full_name,email',
                'order:id,booking_reference,external_booking_id,status',
                'messages' => function ($query): void {
                    $query
                        ->select([
                            'id',
                            'support_ticket_id',
                            'user_id',
                            'message',
                            'attachment_name',
                            'attachment_path',
                            'created_at',
                        ])
                        ->with('user:id,name,full_name,email,account_type');
                },
            ])
            ->whereBelongsTo($user)
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $activeTicket = $activeTickets->first();

        return [
            'active_ticket_count' => $activeTickets->count(),
            'active_ticket' => $activeTicket ? $this->activeSupportTicketPayload($activeTicket) : null,
        ];
    }

    /**
     * Build the CRM support conversation payload for the active user ticket.
     *
     * @return array<string, mixed>
     */
    private function activeSupportTicketPayload(SupportTicket $ticket): array
    {
        $messages = $ticket->messages
            ->take(-8)
            ->values();

        $lastMessage = $messages->last();
        $lastSenderType = $lastMessage?->user?->isAdminAccount() ? 'agent' : ($lastMessage ? 'user' : null);

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'conversation_state' => $this->supportConversationState($lastSenderType),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'assignee' => $ticket->assignee
                ? [
                    'id' => $ticket->assignee->id,
                    'name' => $ticket->assignee->full_name ?: $ticket->assignee->name,
                    'email' => $ticket->assignee->email,
                ]
                : null,
            'order' => $ticket->order
                ? [
                    'id' => $ticket->order->id,
                    'reference' => $ticket->order->booking_reference ?: $ticket->order->external_booking_id ?: 'Order #'.$ticket->order->id,
                    'status' => $ticket->order->status,
                ]
                : null,
            'messages' => $messages
                ->map(fn ($message): array => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender_type' => $message->user?->isAdminAccount() ? 'agent' : 'user',
                    'attachment_name' => $message->attachment_name,
                    'has_attachment' => $message->attachment_path !== null,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'user' => [
                        'id' => $message->user?->id,
                        'name' => $message->user?->full_name ?: $message->user?->name,
                        'email' => $message->user?->email,
                    ],
                ])
                ->values()
                ->all(),
        ];
    }

    private function supportConversationState(?string $lastSenderType): ?string
    {
        return match ($lastSenderType) {
            'user' => 'waiting_for_support',
            'agent' => 'waiting_for_customer',
            default => null,
        };
    }

    /**
     * Build the role payload for the user.
     */
    private function rolePayload(User $user): ?array
    {
        $role = $user->primaryRole();

        if (! $role) {
            return null;
        }

        return [
            'name' => $role->name,
            'label' => $role->label,
        ];
    }

    /**
     * Determine whether the specified user is the last active super admin.
     */
    private function isLastActiveSuperAdmin(User $user): bool
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('name', RbacRegistry::ROLE_SUPER_ADMIN);
            })
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }

    /**
     * Resolve the permission names shown on create/edit forms.
     *
     * @return array<int, string>
     */
    private function formPermissionNames(User $user): array
    {
        if ($user->hasRole(RbacRegistry::ROLE_SUPER_ADMIN)) {
            return RbacRegistry::permissionNames();
        }

        if ($user->hasDirectPermissions()) {
            return $user->directPermissionNames();
        }

        $roleName = $user->primaryRole()?->name;

        return $roleName
            ? (RbacRegistry::rolePermissions()[$roleName] ?? [])
            : [];
    }

    /**
     * Normalize submitted permission names for persistence.
     *
     * @param  array<int, string>|null  $permissionNames
     * @return array<int, string>
     */
    private function normalizePermissionNames(string $roleName, ?array $permissionNames): array
    {
        if ($roleName === RbacRegistry::ROLE_SUPER_ADMIN) {
            return [];
        }

        return collect($permissionNames ?? [])
            ->filter(fn ($permission): bool => is_string($permission) && $permission !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Persist direct permission assignments for the user.
     *
     * @param  array<int, string>  $permissionNames
     */
    private function syncUserPermissions(User $user, string $roleName, array $permissionNames): void
    {
        if (! Schema::hasTable('permission_user')) {
            return;
        }

        if ($roleName === RbacRegistry::ROLE_SUPER_ADMIN) {
            $user->syncPermissionsByName([]);

            return;
        }

        $user->syncPermissionsByName($permissionNames);
    }
}
