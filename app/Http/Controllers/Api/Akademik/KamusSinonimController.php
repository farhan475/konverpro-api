<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKamusSinonimRequest;
use App\Models\KamusSinonim;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KamusSinonimController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $data = KamusSinonim::when($request->filled('search'), function ($q) use ($request) {
                $q->where('kata_utama', 'like', "%{$request->search}%")
                  ->orWhere('sinonim', 'like', "%{$request->search}%");
            })
            ->orderBy('kata_utama')
            ->paginate(30);

        return $this->successResponse($data);
    }

    public function store(StoreKamusSinonimRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['kata_utama'] = strtolower(trim($validated['kata_utama']));
        $validated['sinonim']    = strtolower(trim($validated['sinonim']));
        $validated['created_by'] = $request->user()->id;

        $exists = KamusSinonim::where('kata_utama', $validated['kata_utama'])
            ->where('sinonim', $validated['sinonim'])
            ->exists();

        if ($exists) {
            return $this->errorResponse('Pasangan ini sudah ada di kamus.', 422);
        }

        $kamus = KamusSinonim::create($validated);
        $this->audit->log('kamus.created', 'KamusSinonim', $kamus->id);

        return $this->createdResponse($kamus, 'Entri kamus berhasil ditambahkan.');
    }

    public function destroy(KamusSinonim $kamusSinonim): JsonResponse
    {
        $this->audit->log('kamus.deleted', 'KamusSinonim', $kamusSinonim->id);
        $kamusSinonim->delete();

        return $this->successResponse(null, 'Entri kamus berhasil dihapus.');
    }
}