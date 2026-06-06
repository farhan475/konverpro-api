<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Prodi;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdiController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->successResponse(Prodi::with('kaprodi')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_prodi' => 'required|string|max:100',
            'kode_prodi' => 'nullable|string|max:20',
            'jenjang' => 'required|in:D3,D4,S1,S2',
            'id_kaprodi' => 'nullable|exists:users,id',
        ]);

        $prodi = Prodi::create($validated);
        
        AuditService::log('create_prodi', 'Prodi', $prodi->id, "Created prodi {$prodi->nama_prodi}");

        return $this->successResponse($prodi, 'Prodi created successfully.', 201);
    }

    public function update(Request $request, Prodi $prodi): JsonResponse
    {
        $validated = $request->validate([
            'nama_prodi' => 'string|max:100',
            'kode_prodi' => 'nullable|string|max:20',
            'jenjang' => 'in:D3,D4,S1,S2',
            'id_kaprodi' => 'nullable|exists:users,id',
        ]);

        $prodi->update($validated);
        
        AuditService::log('update_prodi', 'Prodi', $prodi->id, "Updated prodi {$prodi->nama_prodi}");

        return $this->successResponse($prodi, 'Prodi updated successfully.');
    }

    public function destroy(Prodi $prodi): JsonResponse
    {
        $prodi->delete();
        
        AuditService::log('delete_prodi', 'Prodi', $prodi->id, "Deleted prodi {$prodi->nama_prodi}");

        return $this->successResponse(null, 'Prodi deleted successfully.');
    }
}
