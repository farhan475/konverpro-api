<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\KamusSinonim;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KamusSinonimController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(KamusSinonim::with('creator')->latest()->get());
    }

    public function store(\App\Http\Requests\StoreKamusSinonimRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['created_by'] = Auth::id();
        $kamus = KamusSinonim::create($validated);
        
        $this->audit->log('create_kamus', 'KamusSinonim', $kamus->id, "Added synonym: {$kamus->sinonim} -> {$kamus->kata_utama}");

        return $this->successResponse($kamus, 'Kamus entry created successfully.', 201);
    }

    public function update(\App\Http\Requests\StoreKamusSinonimRequest $request, KamusSinonim $kamusSinonim): JsonResponse
    {
        $validated = $request->validated();

        // Check for duplicate (excluding current entry)
        $exists = KamusSinonim::where('kata_utama', strtolower(trim((string) $validated['kata_utama'])))
            ->where('sinonim', strtolower(trim((string) $validated['sinonim'])))
            ->where('id', '!=', $kamusSinonim->id)
            ->exists();

        if ($exists) {
            return $this->errorResponse('Pasangan ini sudah ada di kamus.', 422);
        }

        $kamusSinonim->update($validated);

        $this->audit->log('update_kamus', 'KamusSinonim', $kamusSinonim->id, "Updated synonym: {$kamusSinonim->sinonim} -> {$kamusSinonim->kata_utama}");

        return $this->successResponse($kamusSinonim, 'Kamus entry updated successfully.');
    }

    public function destroy(KamusSinonim $kamusSinonim): JsonResponse
    {
        $kamusSinonim->delete();
        
        $this->audit->log('delete_kamus', 'KamusSinonim', $kamusSinonim->id, "Deleted synonym entry");

        return $this->successResponse(null, 'Kamus entry deleted successfully.');
    }
}