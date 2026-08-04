<?php

namespace App\Modules\Api\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use App\Modules\Api\Wallet\Http\Requests\PayOrderWithWalletRequest;
use App\Modules\Api\Wallet\Http\Requests\TestTopUpRequest;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use App\Exceptions\InsufficientCustomerWalletBalanceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function __construct(
        private readonly CustomerWalletService $walletService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletService->resolveWallet($user);

        return ApiResponse::success($this->serializeWallet($wallet));
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletService->resolveWallet($user, createIfMissing: false);

        $transactions = $wallet->transactions()
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return ApiResponse::success([
            'wallet' => $this->serializeWallet($wallet),
            'transactions' => $transactions->through(fn ($transaction): array => [
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
            ]),
        ], meta: [
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
            'per_page' => $transactions->perPage(),
            'total' => $transactions->total(),
        ]);
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
            $transaction = $this->walletService->testTopUp(
                $request->user(),
                $request->validated('amount'),
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
    private function serializeWallet(\App\Models\CustomerWallet $wallet): array
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
}
