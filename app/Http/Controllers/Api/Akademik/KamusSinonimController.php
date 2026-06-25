<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKamusSinonimRequest;
use App\Models\KamusSinonim;
use App\Models\User;
use App\Services\AuditService;
use App\Services\KamusSinonimService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KamusSinonimController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditService $audit,
        private KamusSinonimService $kamusService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $searchInput = $request->input('search');
        $search = is_string($searchInput) ? $searchInput : '';
        $data = KamusSinonim::when($search !== '', function ($q) use ($search) {
            $q->where('kata_utama', 'like', "%{$search}%")
                ->orWhere('sinonim', 'like', "%{$search}%");
        })
            ->orderBy('kata_utama')
            ->paginate(30);

        return $this->successResponse($data);
    }

    public function store(StoreKamusSinonimRequest $request): JsonResponse
    {
        $validated = $this->kamusService->normalize($request->validated());

        /** @var User $user */
        $user = $request->user();
        $validated['created_by'] = $user->id;

        $this->kamusService->ensureUnique($validated);

        $kamus = KamusSinonim::create($validated);
        $this->audit->log('kamus.created', 'KamusSinonim', $kamus->id);

        return $this->createdResponse($kamus, 'Entri kamus berhasil ditambahkan.');
    }

    public function update(StoreKamusSinonimRequest $request, KamusSinonim $kamusSinonim): JsonResponse
    {
        $validated = $this->kamusService->normalize($request->validated());
        $this->kamusService->ensureUnique($validated, $kamusSinonim);

        $kamusSinonim->update($validated);
        $this->audit->log('kamus.updated', 'KamusSinonim', $kamusSinonim->id);

        return $this->successResponse($kamusSinonim, 'Entri kamus berhasil diperbarui.');
    }

    public function destroy(KamusSinonim $kamusSinonim): JsonResponse
    {
        $this->audit->log('kamus.deleted', 'KamusSinonim', $kamusSinonim->id);
        $kamusSinonim->delete();

        return $this->successResponse(null, 'Entri kamus berhasil dihapus.');
    }
}
