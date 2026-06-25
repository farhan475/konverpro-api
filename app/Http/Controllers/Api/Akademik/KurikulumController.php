<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\HasilKonversi;
use App\Models\KurikulumMk;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KurikulumController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = KurikulumMk::with('prodi');
        if ($request->id_prodi) {
            $query->where('id_prodi', $request->id_prodi);
        }

        return $this->successResponse($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_prodi' => 'required|exists:prodi,id',
            'kode_mk' => [
                'required',
                'string',
                'max:20',
                Rule::unique('kurikulum_mk', 'kode_mk')
                    ->where(fn ($query) => $query->where('id_prodi', $request->input('id_prodi'))),
            ],
            'nama_mk' => 'required|string|max:150',
            'sks' => 'required|integer|min:1',
            'semester' => 'required|integer|min:1',
            'tipe_mk' => 'required|in:Wajib,Pilihan',
        ]);

        $mk = KurikulumMk::create($validated);

        $this->audit->log('create_kurikulum', 'KurikulumMk', $mk->id, "Added MK {$mk->nama_mk}");

        return $this->successResponse($mk, 'Course added successfully.', 201);
    }

    public function show(KurikulumMk $kurikulum): JsonResponse
    {
        return $this->successResponse($kurikulum->load('prodi'));
    }

    public function update(Request $request, KurikulumMk $kurikulum): JsonResponse
    {
        if ($kurikulum->is_locked) {
            return $this->errorResponse('Mata kuliah yang dikunci tidak dapat diubah.', 422);
        }

        $validated = $request->validate([
            'kode_mk' => [
                'string',
                'max:20',
                Rule::unique('kurikulum_mk', 'kode_mk')
                    ->where(fn ($query) => $query->where('id_prodi', $kurikulum->id_prodi))
                    ->ignore($kurikulum->id),
            ],
            'nama_mk' => 'string|max:150',
            'sks' => 'integer|min:1',
            'semester' => 'integer|min:1',
            'tipe_mk' => 'in:Wajib,Pilihan',
        ]);

        $kurikulum->update($validated);

        $this->audit->log('update_kurikulum', 'KurikulumMk', $kurikulum->id, "Updated MK {$kurikulum->nama_mk}");

        return $this->successResponse($kurikulum, 'Course updated successfully.');
    }

    public function destroy(KurikulumMk $kurikulum): JsonResponse
    {
        if ($kurikulum->is_locked || HasilKonversi::where('id_mk_tujuan', $kurikulum->id)->exists()) {
            return $this->errorResponse('Mata kuliah yang dikunci atau sudah digunakan dalam konversi tidak dapat dihapus.', 422);
        }

        $kurikulum->delete();

        $this->audit->log('delete_kurikulum', 'KurikulumMk', $kurikulum->id, "Deleted MK {$kurikulum->nama_mk}");

        return $this->successResponse(null, 'Course deleted successfully.');
    }
}
