<?php

namespace App\Modules\Api\HotelReviews\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HotelReview;
use App\Modules\Api\HotelReviews\Http\Requests\StoreHotelReviewRequest;
use App\Modules\Api\HotelReviews\Services\HotelReviewService;
use App\Modules\Api\Resources\HotelReviewResource;
use App\Modules\Api\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use stdClass;
use Throwable;

class HotelReviewController extends Controller
{
    public function __construct(
        private readonly HotelReviewService $hotelReviewService,
    ) {
    }

    public function store(StoreHotelReviewRequest $request, ?string $bookingId = null): JsonResponse
    {
        $this->authorize('create', HotelReview::class);

        try {
            $review = $this->hotelReviewService->createForUser(
                $request->user(),
                $request->validated(),
                $bookingId,
            );
        } catch (ValidationException $exception) {
            $status = (int) ($exception->status ?: 422);

            $message = match ($status) {
                409 => 'This booking has already been reviewed.',
                404 => 'Hotel booking not found for this account.',
                default => 'Unable to create review.',
            };

            return ApiResponse::error(
                $message,
                $exception->errors(),
                $status === 409 ? 'already_reviewed' : ($status === 404 ? 'not_found' : 'validation_failed'),
                $status,
            );
        }

        return ApiResponse::success(
            HotelReviewResource::make($review)->resolve($request),
            'Review submitted successfully.',
            [],
            201,
        );
    }

    public function showForBooking(Request $request, string $bookingId): JsonResponse
    {
        $this->authorize('viewAny', HotelReview::class);

        try {
            $review = $this->hotelReviewService->findForBooking($request->user(), $bookingId);
        } catch (ValidationException $exception) {
            return ApiResponse::error(
                'Hotel booking not found for this account.',
                $exception->errors(),
                'not_found',
                404,
            );
        }

        if ($review === null) {
            return ApiResponse::success(
                [
                    'review' => null,
                ],
                'No review found for this booking.',
            );
        }

        $this->authorize('view', $review);

        return ApiResponse::success(
            [
                'review' => HotelReviewResource::make($review)->resolve($request),
            ],
            'Review fetched successfully.',
        );
    }

    public function indexForHotel(Request $request, string $hotelId): JsonResponse
    {
        $page = max($request->integer('page', 1), 1);
        $perPage = (int) min(max($request->integer('per_page', 20), 1), 50);

        $reviews = $this->hotelReviewService->paginateForHotel($hotelId, $page, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Hotel reviews fetched successfully.',
            'data' => [
                'hotel_id' => $hotelId,
                'reviews' => HotelReviewResource::collection($reviews->items())->resolve($request),
                'summary' => $this->summaryForHotel($hotelId),
            ],
            'meta' => $this->hotelReviewService->paginationMeta($reviews) ?: new stdClass(),
        ]);
    }

    /**
     * @return array{average_rating: float|null, total: int}
     */
    private function summaryForHotel(string $hotelId): array
    {
        $query = HotelReview::query()->where('hotel_id', $hotelId);

        return [
            'average_rating' => $query->clone()->avg('overall_rating') !== null
                ? round((float) $query->clone()->avg('overall_rating'), 2)
                : null,
            'total' => (int) $query->clone()->count(),
        ];
    }
}
