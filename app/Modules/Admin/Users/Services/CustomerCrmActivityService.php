<?php

namespace App\Modules\Admin\Users\Services;

use App\Models\AiTravelAssistantLog;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\Favorite;
use App\Models\LoyaltyHistory;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\PriceAlert;
use App\Models\RefreshToken;
use App\Models\SavedAddress;
use App\Models\SavedPassenger;
use App\Models\SavedVehicle;
use App\Models\SupportTicket;
use App\Models\TravelSearchIntent;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserNotificationDevice;
use App\Modules\Notifications\Support\NotificationInboxContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerCrmActivityService
{
    private const TIMELINE_LIMIT = 120;

    private const LIST_LIMIT = 40;

    /**
     * Build the CRM activity payload for a customer profile.
     *
     * @return array<string, mixed>
     */
    public function payload(User $user): array
    {
        $searches = $this->searches($user);
        $priceAlerts = $this->priceAlerts($user);
        $notifications = $this->notifications($user);
        $notificationLogs = $this->notificationLogs($user);
        $sessions = $this->sessions($user);
        $sessionHistory = $this->sessionHistory($user);
        $devices = $this->devices($user);
        $tickets = $this->supportTickets($user);
        $wallets = $this->wallets($user);
        $passengers = $this->savedPassengers($user);
        $addresses = $this->savedAddresses($user);
        $vehicles = $this->savedVehicles($user);
        $favorites = $this->favorites($user);
        $aiSearches = $this->aiSearches($user);
        $timeline = $this->timeline($user, $searches, $priceAlerts, $notifications, $tickets, $aiSearches);

        $loginCount = $this->loginCount($user, $notifications, $sessionHistory);

        return [
            'stats' => [
                'login_count' => $loginCount,
                'active_session_count' => count($sessions),
                'search_count' => count($searches),
                'price_alert_count' => count($priceAlerts),
                'notification_count' => count($notifications),
                'unread_notification_count' => collect($notifications)->where('is_unread', true)->count(),
                'notification_log_count' => count($notificationLogs),
                'timeline_count' => count($timeline),
                'ticket_count' => count($tickets),
                'saved_passenger_count' => count($passengers),
                'favorite_count' => count($favorites),
                'ai_search_count' => count($aiSearches),
            ],
            'timeline' => $timeline,
            'searches' => $searches,
            'price_alerts' => $priceAlerts,
            'notifications' => $notifications,
            'notification_logs' => $notificationLogs,
            'sessions' => $sessions,
            'session_history' => $sessionHistory,
            'devices' => $devices,
            'support_tickets' => $tickets,
            'wallets' => $wallets,
            'saved_passengers' => $passengers,
            'saved_addresses' => $addresses,
            'saved_vehicles' => $vehicles,
            'favorites' => $favorites,
            'ai_searches' => $aiSearches,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $searches
     * @param  list<array<string, mixed>>  $priceAlerts
     * @param  list<array<string, mixed>>  $notifications
     * @param  list<array<string, mixed>>  $tickets
     * @param  list<array<string, mixed>>  $aiSearches
     * @return list<array<string, mixed>>
     */
    private function timeline(
        User $user,
        array $searches,
        array $priceAlerts,
        array $notifications,
        array $tickets,
        array $aiSearches,
    ): array {
        $events = collect();

        $events->push($this->event(
            key: 'account.created',
            category: 'account',
            label: 'Account created',
            description: 'Customer profile was created.',
            occurredAt: $user->created_at?->toIso8601String(),
            actor: 'System',
            tone: 'slate',
            source: 'user',
        ));

        if ($user->email_verified_at) {
            $events->push($this->event(
                key: 'account.email_verified',
                category: 'account',
                label: 'Email verified',
                description: 'Email address was verified.',
                occurredAt: $user->email_verified_at->toIso8601String(),
                actor: 'Customer',
                tone: 'emerald',
                source: 'user',
            ));
        }

        if ($user->phone_verified_at) {
            $events->push($this->event(
                key: 'account.phone_verified',
                category: 'account',
                label: 'Phone verified',
                description: 'Phone number was verified.',
                occurredAt: $user->phone_verified_at->toIso8601String(),
                actor: 'Customer',
                tone: 'emerald',
                source: 'user',
            ));
        }

        foreach ($searches as $search) {
            $events->push($this->event(
                key: 'search.'.$search['id'],
                category: 'search',
                label: 'Flight search',
                description: $this->searchDescription($search),
                occurredAt: $search['last_searched_at'],
                actor: 'Customer',
                tone: 'cyan',
                source: 'travel_search_intent',
                meta: $search,
            ));

            if ($search['abandoned_notified_at']) {
                $events->push($this->event(
                    key: 'search.abandoned.'.$search['id'],
                    category: 'notification',
                    label: 'Abandoned search notified',
                    description: $this->searchDescription($search),
                    occurredAt: $search['abandoned_notified_at'],
                    actor: 'System',
                    tone: 'amber',
                    source: 'travel_search_intent',
                ));
            }

            if ($search['price_drop_notified_at']) {
                $events->push($this->event(
                    key: 'search.price_drop.'.$search['id'],
                    category: 'notification',
                    label: 'Price drop notified',
                    description: $this->searchDescription($search),
                    occurredAt: $search['price_drop_notified_at'],
                    actor: 'System',
                    tone: 'emerald',
                    source: 'travel_search_intent',
                ));
            }

            if ($search['converted_at']) {
                $events->push($this->event(
                    key: 'search.converted.'.$search['id'],
                    category: 'order',
                    label: 'Search converted',
                    description: $this->searchDescription($search),
                    occurredAt: $search['converted_at'],
                    actor: 'Customer',
                    tone: 'emerald',
                    source: 'travel_search_intent',
                ));
            }
        }

        foreach ($priceAlerts as $alert) {
            $events->push($this->event(
                key: 'price_alert.'.$alert['id'],
                category: 'alert',
                label: 'Price alert created',
                description: $this->priceAlertDescription($alert),
                occurredAt: $alert['created_at'],
                actor: 'Customer',
                tone: 'violet',
                source: 'price_alert',
            ));

            if ($alert['last_triggered_at']) {
                $events->push($this->event(
                    key: 'price_alert.hit.'.$alert['id'],
                    category: 'notification',
                    label: 'Price alert hit',
                    description: $this->priceAlertDescription($alert),
                    occurredAt: $alert['last_triggered_at'],
                    actor: 'System',
                    tone: 'amber',
                    source: 'price_alert',
                ));
            }
        }

        foreach ($notifications as $notification) {
            $isLogin = ($notification['template_code'] ?? '') === 'LOGIN_ALERT';

            $events->push($this->event(
                key: 'inbox.'.$notification['id'],
                category: $isLogin ? 'login' : 'notification',
                label: $isLogin ? 'Login' : 'Notification sent',
                description: trim(($notification['title'] ?: $notification['template_code'] ?: 'Notification').' — '.($notification['message'] ?: '')),
                occurredAt: $notification['created_at'],
                actor: $isLogin ? 'Customer' : 'System',
                tone: $isLogin ? 'cyan' : ($notification['is_unread'] ? 'amber' : 'violet'),
                source: 'user_notification',
                meta: [
                    'template_code' => $notification['template_code'],
                    'read_at' => $notification['read_at'],
                ],
            ));

        }

        $loginNotifications = collect($notifications)->where('template_code', 'LOGIN_ALERT');

        if ($loginNotifications->isEmpty() && $user->last_login_at) {
            $events->push($this->event(
                key: 'login.last',
                category: 'login',
                label: 'Last login',
                description: 'Last recorded sign-in.',
                occurredAt: $user->last_login_at->toIso8601String(),
                actor: 'Customer',
                tone: 'cyan',
                source: 'user',
            ));
        }

        foreach ($tickets as $ticket) {
            $events->push($this->event(
                key: 'support.'.$ticket['id'],
                category: 'support',
                label: 'Support ticket',
                description: trim(($ticket['ticket_number'] ?: '').' · '.($ticket['subject'] ?: '')),
                occurredAt: $ticket['created_at'],
                actor: 'Customer',
                tone: 'amber',
                source: 'support_ticket',
            ));
        }

        foreach ($aiSearches as $log) {
            $events->push($this->event(
                key: 'ai.'.$log['id'],
                category: 'search',
                label: 'AI travel search',
                description: trim(($log['intent'] ?: $log['mode'] ?: 'AI').' · '.($log['message'] ?: '')),
                occurredAt: $log['created_at'],
                actor: 'Customer',
                tone: 'violet',
                source: 'ai_travel_assistant',
            ));
        }

        $this->appendOrders($events, $user);
        $this->appendOrderHistories($events, $user);
        $this->appendWalletTransactions($events, $user);
        $this->appendLoyaltyHistory($events, $user);
        $this->appendProfileActions($events, $user);

        return $events
            ->filter(fn (array $event): bool => filled($event['occurred_at'] ?? null))
            ->unique('key')
            ->sortByDesc(fn (array $event): int => strtotime((string) $event['occurred_at']) ?: 0)
            ->values()
            ->take(self::TIMELINE_LIMIT)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searches(User $user): array
    {
        if (! Schema::hasTable('travel_search_intents')) {
            return [];
        }

        return TravelSearchIntent::query()
            ->whereBelongsTo($user)
            ->orderByDesc('last_searched_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (TravelSearchIntent $intent): array => [
                'id' => $intent->id,
                'origin' => $intent->origin,
                'destination' => $intent->destination,
                'route' => $intent->origin.' → '.$intent->destination,
                'departure_date' => $intent->departure_date?->toDateString(),
                'return_date' => $intent->return_date?->toDateString(),
                'last_seen_price' => $intent->last_seen_price !== null ? number_format((float) $intent->last_seen_price, 2, '.', '') : null,
                'previous_seen_price' => $intent->previous_seen_price !== null ? number_format((float) $intent->previous_seen_price, 2, '.', '') : null,
                'currency' => $intent->currency,
                'last_searched_at' => $intent->last_searched_at?->toIso8601String(),
                'abandoned_notified_at' => $intent->abandoned_notified_at?->toIso8601String(),
                'price_drop_notified_at' => $intent->price_drop_notified_at?->toIso8601String(),
                'converted_at' => $intent->converted_at?->toIso8601String(),
                'status' => $intent->converted_at
                    ? 'converted'
                    : ($intent->abandoned_notified_at ? 'abandoned' : 'active'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function priceAlerts(User $user): array
    {
        if (! Schema::hasTable('price_alerts')) {
            return [];
        }

        return PriceAlert::query()
            ->whereBelongsTo($user)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (PriceAlert $alert): array => [
                'id' => $alert->id,
                'origin' => $alert->origin,
                'destination' => $alert->destination,
                'route' => $alert->origin.' → '.$alert->destination,
                'departure_date' => $alert->departure_date?->toDateString(),
                'target_price' => number_format((float) $alert->target_price, 2, '.', ''),
                'last_triggered_price' => $alert->last_triggered_price !== null ? number_format((float) $alert->last_triggered_price, 2, '.', '') : null,
                'currency' => $alert->currency,
                'is_active' => (bool) $alert->is_active,
                'last_triggered_at' => $alert->last_triggered_at?->toIso8601String(),
                'created_at' => $alert->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notifications(User $user): array
    {
        if (! Schema::hasTable('user_notifications')) {
            return [];
        }

        return UserNotification::query()
            ->whereBelongsTo($user)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(function (UserNotification $notification): array {
                $code = (string) ($notification->template_code ?: '');

                return [
                    'id' => $notification->id,
                    'template_code' => $notification->template_code,
                    'category' => $code !== '' ? NotificationInboxContract::category($code) : ($notification->type ?: 'general'),
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'related_type' => $notification->related_type,
                    'related_id' => $notification->related_id,
                    'is_unread' => $notification->isUnread(),
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'delivered_at' => $notification->delivered_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notificationLogs(User $user): array
    {
        if (! Schema::hasTable('notification_logs')) {
            return [];
        }

        return NotificationLog::query()
            ->whereBelongsTo($user)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (NotificationLog $log): array => [
                'id' => $log->id,
                'channel' => $log->channel,
                'template_code' => $log->template_code,
                'subject' => $log->subject,
                'status' => $log->status,
                'retry_count' => (int) $log->retry_count,
                'sent_at' => $log->sent_at?->toIso8601String(),
                'failed_at' => $log->failed_at?->toIso8601String(),
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sessions(User $user): array
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return [];
        }

        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'device_name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sessionHistory(User $user): array
    {
        if (! Schema::hasTable('refresh_tokens')) {
            return [];
        }

        return RefreshToken::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (RefreshToken $token): array => [
                'id' => $token->id,
                'device_name' => $token->device_name,
                'remember_me' => (bool) $token->remember_me,
                'expires_at' => $token->expires_at?->toIso8601String(),
                'revoked_at' => $token->revoked_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devices(User $user): array
    {
        if (! Schema::hasTable('user_notification_devices')) {
            return [];
        }

        return UserNotificationDevice::query()
            ->whereBelongsTo($user)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (UserNotificationDevice $device): array => [
                'id' => $device->id,
                'channel' => $device->channel,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'is_active' => (bool) $device->is_active,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'created_at' => $device->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function supportTickets(User $user): array
    {
        if (! Schema::hasTable('support_tickets')) {
            return [];
        }

        return SupportTicket::query()
            ->whereBelongsTo($user)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function wallets(User $user): array
    {
        if (! Schema::hasTable('customer_wallets')) {
            return [];
        }

        return CustomerWallet::query()
            ->where('user_id', $user->id)
            ->orderBy('currency')
            ->get()
            ->map(function (CustomerWallet $wallet): array {
                $transactions = Schema::hasTable('customer_wallet_transactions')
                    ? $wallet->transactions()
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->limit(10)
                        ->get()
                        ->map(fn (CustomerWalletTransaction $transaction): array => [
                            'id' => $transaction->id,
                            'type' => $transaction->type,
                            'amount' => number_format((float) $transaction->amount, 2, '.', ''),
                            'signed_amount' => $transaction->signedAmount(),
                            'currency' => $transaction->currency,
                            'description' => $transaction->description,
                            'created_at' => $transaction->created_at?->toIso8601String(),
                        ])
                        ->values()
                        ->all()
                    : [];

                return [
                    'id' => $wallet->id,
                    'wallet_number' => $wallet->wallet_number,
                    'currency' => $wallet->currency,
                    'balance' => number_format((float) $wallet->balance, 2, '.', ''),
                    'status' => $wallet->status,
                    'transactions' => $transactions,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function savedPassengers(User $user): array
    {
        if (! Schema::hasTable('saved_passengers')) {
            return [];
        }

        return SavedPassenger::query()
            ->whereBelongsTo($user)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (SavedPassenger $passenger): array => [
                'id' => $passenger->id,
                'type' => $passenger->type,
                'name' => trim(($passenger->first_name ?: '').' '.($passenger->last_name ?: '')) ?: $passenger->title,
                'nationality' => $passenger->nationality,
                'document_type' => $passenger->document_type,
                'is_default' => (bool) $passenger->is_default,
                'created_at' => $passenger->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function savedAddresses(User $user): array
    {
        if (! Schema::hasTable('saved_addresses')) {
            return [];
        }

        return SavedAddress::query()
            ->whereBelongsTo($user)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (SavedAddress $address): array => [
                'id' => $address->id,
                'title' => $address->title,
                'address' => $address->address,
                'is_default' => (bool) $address->is_default,
                'created_at' => $address->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function savedVehicles(User $user): array
    {
        if (! Schema::hasTable('saved_vehicles')) {
            return [];
        }

        return SavedVehicle::query()
            ->whereBelongsTo($user)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (SavedVehicle $vehicle): array => [
                'id' => $vehicle->id,
                'type' => $vehicle->type,
                'label' => $vehicle->label ?: $vehicle->vehicle_plate_number,
                'is_default' => (bool) $vehicle->is_default,
                'created_at' => $vehicle->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function favorites(User $user): array
    {
        if (! Schema::hasTable('favorites')) {
            return [];
        }

        return Favorite::query()
            ->whereBelongsTo($user)
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(function (Favorite $favorite): array {
                $snapshot = is_array($favorite->snapshot) ? $favorite->snapshot : [];

                return [
                    'id' => $favorite->id,
                    'type' => $favorite->type,
                    'item_key' => $favorite->item_key,
                    'status' => $favorite->status,
                    'title' => $snapshot['title'] ?? $snapshot['name'] ?? $snapshot['hotel_name'] ?? $favorite->item_key,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aiSearches(User $user): array
    {
        if (! Schema::hasTable('ai_travel_assistant_logs')) {
            return [];
        }

        return AiTravelAssistantLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (AiTravelAssistantLog $log): array => [
                'id' => $log->id,
                'mode' => $log->mode,
                'intent' => $log->intent,
                'product' => $log->product,
                'message' => $log->message,
                'success' => (bool) $log->success,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $notifications
     * @param  list<array<string, mixed>>  $sessionHistory
     */
    private function loginCount(User $user, array $notifications, array $sessionHistory): int
    {
        $fromAlerts = collect($notifications)
            ->where('template_code', 'LOGIN_ALERT')
            ->count();

        if ($fromAlerts > 0) {
            return $fromAlerts;
        }

        $fromSessions = count($sessionHistory);

        if ($fromSessions > 0) {
            return $fromSessions;
        }

        return $user->last_login_at ? 1 : 0;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendOrders(Collection $events, User $user): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Order::query()
            ->select(['id', 'booking_reference', 'status', 'service_type', 'total_amount', 'currency', 'created_at'])
            ->whereBelongsTo($user, 'customer')
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->each(function (Order $order) use ($events): void {
                $events->push($this->event(
                    key: 'order.'.$order->id,
                    category: 'order',
                    label: 'Order placed',
                    description: trim(($order->booking_reference ?: '#'.$order->id).' · '.($order->status ?: '').' · '.($order->total_amount ?? '').' '.($order->currency ?? '')),
                    occurredAt: $order->created_at?->toIso8601String(),
                    actor: 'Customer',
                    tone: 'emerald',
                    source: 'order',
                ));
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendOrderHistories(Collection $events, User $user): void
    {
        if (! Schema::hasTable('order_histories')) {
            return;
        }

        OrderHistory::query()
            ->with(['order:id,booking_reference'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->each(function (OrderHistory $history) use ($events): void {
                $events->push($this->event(
                    key: 'order.history.'.$history->id,
                    category: 'order',
                    label: $this->humanize((string) ($history->action ?: $history->field ?: 'updated')),
                    description: trim(($history->order?->booking_reference ?: 'Order #'.$history->order_id).' · '.($history->old_value ?? '—').' → '.($history->new_value ?? '—')),
                    occurredAt: $history->created_at?->toIso8601String(),
                    actor: 'Customer',
                    tone: 'violet',
                    source: 'order_history',
                ));
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendWalletTransactions(Collection $events, User $user): void
    {
        if (! Schema::hasTable('customer_wallets') || ! Schema::hasTable('customer_wallet_transactions')) {
            return;
        }

        $walletIds = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        if ($walletIds->isEmpty()) {
            return;
        }

        CustomerWalletTransaction::query()
            ->whereIn('customer_wallet_id', $walletIds)
            ->orderByDesc('created_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->each(function (CustomerWalletTransaction $transaction) use ($events): void {
                $events->push($this->event(
                    key: 'wallet.'.$transaction->id,
                    category: 'finance',
                    label: 'Wallet '.$this->humanize((string) $transaction->type),
                    description: trim($transaction->signedAmount().' '.($transaction->currency ?? '').' · '.($transaction->description ?: '')),
                    occurredAt: $transaction->created_at?->toIso8601String(),
                    actor: $transaction->created_by ? 'Admin' : 'System',
                    tone: $transaction->isDebit() ? 'rose' : 'emerald',
                    source: 'customer_wallet',
                ));
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendLoyaltyHistory(Collection $events, User $user): void
    {
        if (! Schema::hasTable('loyalty_history')) {
            return;
        }

        LoyaltyHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('changed_at')
            ->limit(20)
            ->get()
            ->each(function (LoyaltyHistory $entry) use ($events): void {
                $events->push($this->event(
                    key: 'loyalty.'.$entry->id,
                    category: 'loyalty',
                    label: 'Loyalty '.$this->humanize((string) $entry->action),
                    description: $entry->notes ?: 'Loyalty tier change.',
                    occurredAt: $entry->changed_at?->toIso8601String() ?: $entry->created_at?->toIso8601String(),
                    actor: 'System',
                    tone: 'amber',
                    source: 'loyalty_history',
                ));
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     */
    private function appendProfileActions(Collection $events, User $user): void
    {
        if (Schema::hasTable('saved_passengers')) {
            SavedPassenger::query()
                ->whereBelongsTo($user)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->each(function (SavedPassenger $passenger) use ($events): void {
                    $name = trim(($passenger->first_name ?: '').' '.($passenger->last_name ?: '')) ?: $passenger->title;
                    $events->push($this->event(
                        key: 'passenger.'.$passenger->id,
                        category: 'profile',
                        label: 'Saved passenger',
                        description: trim($name.' · '.($passenger->type ?: '')),
                        occurredAt: $passenger->created_at?->toIso8601String(),
                        actor: 'Customer',
                        tone: 'slate',
                        source: 'saved_passenger',
                    ));
                });
        }

        if (Schema::hasTable('favorites')) {
            Favorite::query()
                ->whereBelongsTo($user)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->each(function (Favorite $favorite) use ($events): void {
                    $snapshot = is_array($favorite->snapshot) ? $favorite->snapshot : [];
                    $title = $snapshot['title'] ?? $snapshot['name'] ?? $favorite->item_key;
                    $events->push($this->event(
                        key: 'favorite.'.$favorite->id,
                        category: 'profile',
                        label: 'Saved favorite',
                        description: trim($this->humanize((string) $favorite->type).' · '.$title),
                        occurredAt: $favorite->created_at?->toIso8601String(),
                        actor: 'Customer',
                        tone: 'violet',
                        source: 'favorite',
                    ));
                });
        }
    }

    /**
     * @param  array<string, mixed>  $search
     */
    private function searchDescription(array $search): string
    {
        $parts = [
            $search['route'] ?? '',
            $search['departure_date'] ?? null,
            isset($search['last_seen_price']) ? ($search['last_seen_price'].' '.($search['currency'] ?? '')) : null,
            $search['status'] ?? null,
        ];

        return implode(' · ', array_filter($parts, fn ($value): bool => filled($value)));
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function priceAlertDescription(array $alert): string
    {
        return implode(' · ', array_filter([
            $alert['route'] ?? '',
            isset($alert['target_price']) ? 'target '.$alert['target_price'].' '.($alert['currency'] ?? '') : null,
            $alert['is_active'] ?? false ? 'active' : 'inactive',
        ], fn ($value): bool => filled($value)));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function event(
        string $key,
        string $category,
        string $label,
        string $description,
        ?string $occurredAt,
        string $actor,
        string $tone,
        string $source,
        array $meta = [],
    ): array {
        return [
            'key' => $key,
            'category' => $category,
            'label' => $label,
            'description' => $description,
            'occurred_at' => $occurredAt,
            'actor' => $actor,
            'tone' => $tone,
            'source' => $source,
            'meta' => $meta === [] ? null : $meta,
        ];
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', $value));
    }
}
