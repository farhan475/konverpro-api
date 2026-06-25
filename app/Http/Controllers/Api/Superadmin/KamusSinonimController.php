<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKamusSinonimRequest;
use App\Models\KamusSinonim;
use App\Services\AuditService;
use App\Services\KamusSinonimService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class KamusSinonimController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditService $audit,
        private KamusSinonimService $kamusService
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(KamusSinonim::with('creator')->latest()->get());
    }

    public function store(StoreKamusSinonimRequest $request): JsonResponse
    {
        $validated = $this->kamusService->normalize($request->validated());
        $this->kamusService->ensureUnique($validated);

        $validated['created_by'] = Auth::id();
        $kamus = KamusSinonim::create($validated);

        $this->audit->log('create_kamus', 'KamusSinonim', $kamus->id, "Added synonym: {$kamus->sinonim} -> {$kamus->kata_utama}");

        return $this->successResponse($kamus, 'Kamus entry created successfully.', 201);
    }

    public function update(StoreKamusSinonimRequest $request, KamusSinonim $kamusSinonim): JsonResponse
    {
        $validated = $this->kamusService->normalize($request->validated());
        $this->kamusService->ensureUnique($validated, $kamusSinonim);

        $kamusSinonim->update($validated);

        $this->audit->log('update_kamus', 'KamusSinonim', $kamusSinonim->id, "Updated synonym: {$kamusSinonim->sinonim} -> {$kamusSinonim->kata_utama}");

        return $this->successResponse($kamusSinonim, 'Kamus entry updated successfully.');
    }

    public function destroy(KamusSinonim $kamusSinonim): JsonResponse
    {
        $kamusSinonim->delete();

        $this->audit->log('delete_kamus', 'KamusSinonim', $kamusSinonim->id, 'Deleted synonym entry');

        return $this->successResponse(null, 'Kamus entry deleted successfully.');
    }
}
