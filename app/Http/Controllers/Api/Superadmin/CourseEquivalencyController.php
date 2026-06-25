<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\CourseEquivalency;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseEquivalencyController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = CourseEquivalency::with('mkTujuan.prodi')->latest('updated_at');
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($builder) => $builder
                ->where('asal_kampus', 'like', "%{$search}%")
                ->orWhere('nama_mk_asal', 'like', "%{$search}%"));
        }

        return $this->successResponse($query->paginate(30));
    }

    public function update(Request $request, CourseEquivalency $equivalency): JsonResponse
    {
        $validated = $request->validate([
            'valid_until' => 'nullable|date|after_or_equal:today',
            'is_active' => 'required|boolean',
            'alasan' => 'nullable|string|max:1000',
        ]);
        $equivalency->update($validated);
        $this->audit->log(
            'course_equivalency.updated',
            'CourseEquivalency',
            $equivalency->id,
            $validated['is_active'] ? 'Referensi diaktifkan.' : 'Referensi dinonaktifkan.'
        );

        return $this->successResponse($equivalency->fresh('mkTujuan.prodi'), 'Referensi ekuivalensi diperbarui.');
    }
}
