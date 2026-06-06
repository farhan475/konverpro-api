<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\KamusSinonim;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KamusSinonimController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->successResponse(KamusSinonim::with('creator')->latest()->get());
    }

    public function store(\App\Http\Requests\StoreKamusSinonimRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['created_by'] = auth()->id();
        $kamus = KamusSinonim::create($validated);
        
        AuditService::log('create_kamus', 'KamusSinonim', $kamus->id, "Added synonym: {$kamus->sinonim} -> {$kamus->kata_utama}");

        return $this->successResponse($kamus, 'Kamus entry created successfully.', 201);
    }

    public function destroy(KamusSinonim $kamusSinonim): JsonResponse
    {
        $kamusSinonim->delete();
        
        AuditService::log('delete_kamus', 'KamusSinonim', $kamusSinonim->id, "Deleted synonym entry");

        return $this->successResponse(null, 'Kamus entry deleted successfully.');
    }
}
