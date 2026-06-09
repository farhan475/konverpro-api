<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Prodi;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdiController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            Prodi::with('kaprodi:id,nama_lengkap,email')->orderBy('nama_prodi')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_prodi' => 'required|string|max:100',
            'kode_prodi' => 'nullable|string|max:20|unique:prodi,kode_prodi',
            'jenjang'    => 'required|in:D3,D4,S1,S2',
            'id_kaprodi' => 'nullable|exists:users,id',
        ]);

        $prodi = Prodi::create($validated);
        $this->audit->log('prodi.created', 'Prodi', $prodi->id, $prodi->nama_prodi);

        return $this->createdResponse($prodi, 'Prodi berhasil dibuat.');
    }

    public function update(Request $request, Prodi $prodi): JsonResponse
    {
        $validated = $request->validate([
            'nama_prodi' => 'sometimes|string|max:100',
            'kode_prodi' => "nullable|string|max:20|unique:prodi,kode_prodi,{$prodi->id}",
            'jenjang'    => 'sometimes|in:D3,D4,S1,S2',
            'id_kaprodi' => 'nullable|exists:users,id',
        ]);

        $prodi->update($validated);
        $this->audit->log('prodi.updated', 'Prodi', $prodi->id, $prodi->nama_prodi);

        return $this->successResponse($prodi->load('kaprodi:id,nama_lengkap'), 'Prodi berhasil diperbarui.');
    }

    public function destroy(Prodi $prodi): JsonResponse
    {
        if ($prodi->pendaftar()->exists()) {
            return $this->errorResponse('Prodi tidak dapat dihapus karena masih memiliki data pendaftar.', 422);
        }

        $this->audit->log('prodi.deleted', 'Prodi', $prodi->id, $prodi->nama_prodi);
        $prodi->delete();

        return $this->successResponse(null, 'Prodi berhasil dihapus.');
    }
}