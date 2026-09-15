<?php

namespace App\Modules\Api\Wallet\Http\Controllers;

use App\Exceptions\InsufficientCustomerWalletBalanceException;
use App\Http\Controllers\Controller;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\Order;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Api\Wallet\Http\Requests\PayOrderWithWalletRequest;
use App\Modules\Api\Wallet\Http\Requests\TestTopUpRequest;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function __construct(
        private readonly CustomerWalletService $walletService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallets = $this->walletService->listWallets($user);
        $defaultCurrency = (string) config('customer_wallets.default_currency', 'LYD');

        return ApiResponse::success([
            'default_currency' => $defaultCurrency,
            'supported_currencies' => $this->walletService->supportedCurrencies(),
            'wallets' => array_map(fn (CustomerWallet $wallet): array => $this->serializeWallet($wallet), $wallets),
            'test_mode_enabled' => (bool) config('customer_wallets.test_mode'),
        ]);
    }

    public function show(Request $request, string $currency): JsonResponse
    {
        try {
            $currency = $this->walletService->normalizeCurrency($currency);
        } catch (ValidationException $exception) {
            return ApiResponse::validation($exception->errors());
        }

        $wallet = $this->walletService->resolveWallet($request->user(), $currency);

        return ApiResponse::success($this->serializeWallet($wallet));
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $currency = $this->walletService->normalizeCurrency(
                (string) $request->query('currency', config('customer_wallets.default_currency', 'LYD'))
            );

            $this->walletService->ensureSupportedWallets($user);
            $wallet = $this->walletService->resolveWallet($user, $currency, createIfMissing: false);
        } catch (ValidationException $exception) {
            return ApiResponse::validation($exception->errors());
        }

        $perPage = (int) min(max($request->integer('per_page', 20), 1), 100);
        $page = max($request->integer('page', 1), 1);

        $paginator = $wallet->transactions()
            ->where('currency', $currency)
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn ($transaction): array => $this->serializeTransaction($transaction))
            ->values()
            ->all();

        $pagination = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return ApiResponse::success([
            'wallet' => $this->serializeWallet($wallet),
            // Flat list for mobile clients (do not nest Laravel paginator `data`).
            'customer_wallet_transactions' => $items,
            'transactions' => $items,
            ...$pagination,
        ], meta: $pagination);
    }

    public function testTopUp(TestTopUpRequest $request): JsonResponse
    {
        if (! config('customer_wallets.test_mode')) {
            return ApiResponse::error(
                'Test wallet top-up is disabled.',
                [],
                'wallet_test_disabled',
                403,
            );
        }

        try {
            $validated = $request->validated();
            $transaction = $this->walletService->testTopUp(
                $request->user(),
                $validated['amount'],
                $validated['currency'] ?? null,
            );

            $wallet = $transaction->wallet->refresh();

            return ApiResponse::success([
                'wallet' => $this->serializeWallet($wallet),
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'currency' => $transaction->currency,
                ],
            ], 'Test top-up completed.');
        } catch (ValidationException $exception) {
            return ApiResponse::validation($exception->errors());
        }
    }

    public function payOrder(PayOrderWithWalletRequest $request): JsonResponse
    {
        $order = Order::query()->findOrFail($request->validated('order_id'));

        try {
            $this->walletService->ensureSupportedWallets($request->user());
            $transaction = $this->walletService->payForOrder($order, $request->user());
            $wallet = $transaction->wallet->refresh();

            return ApiResponse::success([
                'wallet' => $this->serializeWallet($wallet),
                'order' => [
                    'id' => $order->id,
                    'payment_status' => $order->refresh()->payment_status,
                    'payment_method' => $order->payment_method,
                    'status' => $order->status,
                ],
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'currency' => $transaction->currency,
                ],
            ], 'Order paid with wallet.');
        } catch (InsufficientCustomerWalletBalanceException $exception) {
            return ApiResponse::error(
                'Insufficient wallet balance.',
                [
                    'requested_amount' => $exception->requestedAmount,
                    'available_balance' => $exception->availableBalance,
                ],
                'insufficient_wallet_balance',
                422,
            );
        } catch (ValidationException $exception) {
            return ApiResponse::validation($exception->errors());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWallet(CustomerWallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'wallet_number' => $wallet->wallet_number,
            'currency' => $wallet->currency,
            'balance' => $wallet->balance,
            'status' => $wallet->status,
            'is_frozen' => $wallet->isFrozen(),
            'test_mode_enabled' => (bool) config('customer_wallets.test_mode'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(CustomerWalletTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'signed_amount' => $transaction->signedAmount(),
            'balance_before' => $transaction->balance_before,
            'balance_after' => $transaction->balance_after,
            'currency' => $transaction->currency,
            'description' => $transaction->description,
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
            'order_id' => $transaction->order_id,
            'created_at' => optional($transaction->created_at)?->toIso8601String(),
        ];
    }
}
