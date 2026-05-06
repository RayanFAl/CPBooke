<?php

namespace App\Modules\Api\Support\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

class ApiResponse
{
    /**
     * Build a successful JSON response.
     *
     * @param  array<string, mixed>|JsonResource  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(array|JsonResource $data = [], string $message = '', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::normalizeData($data),
            'meta' => $meta === [] ? new stdClass() : $meta,
        ], $status);
    }

    /**
     * Build an error JSON response.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function error(string $message = '', array $errors = [], ?string $code = null, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? new stdClass() : $errors,
            'code' => $code,
        ], $status);
    }

    /**
     * Build a validation JSON response.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function validation(array $errors, string $message = 'The given data was invalid.', ?string $code = 'validation_failed', int $status = 422): JsonResponse
    {
        return self::error($message, $errors, $code, $status);
    }

    /**
     * Normalize payload data for JSON responses.
     *
     * @param  array<string, mixed>|JsonResource  $data
     * @return array<string, mixed>|stdClass
     */
    private static function normalizeData(array|JsonResource $data): array|stdClass
    {
        if ($data instanceof JsonResource) {
            /** @var array<string, mixed> $resolved */
            $resolved = $data->resolve(request());

            return $resolved === [] ? new stdClass() : $resolved;
        }

        return $data === [] ? new stdClass() : $data;
    }
}