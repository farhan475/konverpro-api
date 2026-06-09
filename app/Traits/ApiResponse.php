<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        int $code = 200
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data instanceof LengthAwarePaginator ? $data->items() : $data,
        ];

        if ($data instanceof LengthAwarePaginator) {
            $payload['meta'] = [
                'current_page' => $data->currentPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
                'last_page'    => $data->lastPage(),
            ];
        }

        return response()->json($payload, $code);
    }

    protected function createdResponse(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function errorResponse(?string $message = null, int $code = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    protected function notFoundResponse(string $message = 'Data tidak ditemukan.'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    protected function unauthorizedResponse(string $message = 'Tidak memiliki akses.'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }
}