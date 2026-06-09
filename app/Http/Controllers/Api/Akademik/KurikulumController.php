<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\KurikulumMk;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KurikulumController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $data = KurikulumMk::with('prodi:id,nama_prodi,kode_prodi')
            ->when($request->filled('id_prodi'), fn($q) => $q->where('id_prodi', $request->id_prodi))
            ->orderBy('semester')
            ->orderBy('nama_mk')
            ->paginate(50);

        return $this->successResponse($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'kode_mk'  => 'nullable|string|max:20',
            'nama_mk'  => 'required|string|max:150',
            'sks'      => 'required|integer|min:1|max:6',
            'semester' => 'required|integer|min:1|max:14',
            'tipe_mk'  => 'required|in:Wajib,Pilihan',
        ]);

        $mk = KurikulumMk::create($validated);
        $this->audit->log('kurikulum.created', 'KurikulumMk', $mk->id, "Added MK {$mk->nama_mk}");

        return $this->createdResponse($mk->load('prodi:id,nama_prodi'), 'Mata kuliah berhasil ditambahkan.');
    }

    public function update(Request $request, KurikulumMk $kurikulum): JsonResponse
    {
        if ($kurikulum->is_locked) {
            return $this->errorResponse('Mata kuliah ini terkunci dan tidak dapat diubah.', 422);
        }

        $validated = $request->validate([
            'kode_mk'  => 'nullable|string|max:20',
            'nama_mk'  => 'sometimes|string|max:150',
            'sks'      => 'sometimes|integer|min:1|max:6',
            'semester' => 'sometimes|integer|min:1|max:14',
            'tipe_mk'  => 'sometimes|in:Wajib,Pilihan',
        ]);

        $kurikulum->update($validated);
        $this->audit->log('kurikulum.updated', 'KurikulumMk', $kurikulum->id, "Updated MK {$kurikulum->nama_mk}");

        return $this->successResponse($kurikulum, 'Mata kuliah berhasil diperbarui.');
    }

    public function destroy(KurikulumMk $kurikulum): JsonResponse
    {
        if ($kurikulum->is_locked) {
            return $this->errorResponse('Mata kuliah ini terkunci dan tidak dapat dihapus.', 422);
        }

        $this->audit->log('kurikulum.deleted', 'KurikulumMk', $kurikulum->id, "Deleted MK {$kurikulum->nama_mk}");
        $kurikulum->delete();

        return $this->successResponse(null, 'Mata kuliah berhasil dihapus.');
    }
}