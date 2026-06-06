<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @param mixed $meta
     */
    protected function successResponse(mixed $data = null, ?string $message = null, int $code = 200, mixed $meta = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], $code);
    }

    /**
     * @param string|null $message
     * @param int $code
     * @param mixed $data
     */
    protected function errorResponse(?string $message = null, int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }
}
