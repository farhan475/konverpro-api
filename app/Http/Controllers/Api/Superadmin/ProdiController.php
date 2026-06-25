<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Prodi;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProdiController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(Prodi::with('kaprodi')->get());
    }

    public function reference(): JsonResponse
    {
        return $this->successResponse(
            Prodi::query()
                ->orderBy('nama_prodi')
                ->get(['id', 'kode_prodi', 'nama_prodi', 'jenjang'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_prodi' => 'required|string|max:100|unique:prodi,nama_prodi',
            'kode_prodi' => 'nullable|string|max:20|unique:prodi,kode_prodi',
            'jenjang' => 'required|in:D3,D4,S1,S2',
            'id_kaprodi' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'kaprodi')
                    ->where('status', 'active')),
            ],
        ]);

        $prodi = Prodi::create($validated);

        $this->audit->log('create_prodi', 'Prodi', $prodi->id, "Created prodi {$prodi->nama_prodi}");

        return $this->successResponse($prodi, 'Prodi created successfully.', 201);
    }

    public function show(Prodi $prodi): JsonResponse
    {
        return $this->successResponse($prodi->load('kaprodi', 'pengaturan'));
    }

    public function update(Request $request, Prodi $prodi): JsonResponse
    {
        $validated = $request->validate([
            'nama_prodi' => ['string', 'max:100', Rule::unique('prodi', 'nama_prodi')->ignore($prodi->id)],
            'kode_prodi' => ['nullable', 'string', 'max:20', Rule::unique('prodi', 'kode_prodi')->ignore($prodi->id)],
            'jenjang' => 'in:D3,D4,S1,S2',
            'id_kaprodi' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'kaprodi')
                    ->where('status', 'active')),
            ],
        ]);

        $prodi->update($validated);

        $this->audit->log('update_prodi', 'Prodi', $prodi->id, "Updated prodi {$prodi->nama_prodi}");

        return $this->successResponse($prodi, 'Prodi updated successfully.');
    }

    public function destroy(Prodi $prodi): JsonResponse
    {
        if ($prodi->pendaftar()->exists()) {
            return $this->errorResponse('Program studi yang sudah memiliki data pendaftar tidak dapat dihapus.', 422);
        }

        $prodi->delete();

        $this->audit->log('delete_prodi', 'Prodi', $prodi->id, "Deleted prodi {$prodi->nama_prodi}");

        return $this->successResponse(null, 'Prodi deleted successfully.');
    }

    public function getSettings(Prodi $prodi): JsonResponse
    {
        return $this->successResponse($prodi->pengaturan()->firstOrCreate(['id_prodi' => $prodi->id]));
    }

    public function updateSettings(Request $request, Prodi $prodi): JsonResponse
    {
        $validated = $request->validate([
            'min_nilai_huruf' => 'string|max:2',
            'max_konversi_sks_persen' => 'integer|min:0|max:100',
            'format_no_ba' => 'string|max:100',
            'metode_pengakuan' => 'in:direct,scale',
        ]);

        $settings = $prodi->pengaturan()->updateOrCreate(['id_prodi' => $prodi->id], $validated);

        $this->audit->log('update_prodi_settings', 'Prodi', $prodi->id, "Updated settings for prodi {$prodi->nama_prodi}");

        return $this->successResponse($settings, 'Prodi settings updated successfully.');
    }
}
