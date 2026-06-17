<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')->latest();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('details', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->input('action'));
        }

        return $this->successResponse($query->paginate(50));
    }
}
