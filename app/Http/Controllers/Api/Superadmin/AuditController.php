<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse|StreamedResponse
    {
        $query = AuditLog::with('user')->latest();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qu) use ($search) {
                        $qu->where('nama_lengkap', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->input('action'));
        }

        if ($request->query('format') === 'csv') {
            $rows = $query->get();

            return response()->streamDownload(function () use ($rows): void {
                $output = fopen('php://output', 'w');
                if ($output === false) {
                    return;
                }

                fputcsv($output, ['Waktu', 'Pengguna', 'Role', 'Aksi', 'Subjek', 'ID Subjek', 'IP', 'Detail']);
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->created_at?->format('Y-m-d H:i:s'),
                        $row->user?->nama_lengkap,
                        $row->user?->role->value,
                        $row->action,
                        $row->subject_type,
                        $row->subject_id,
                        $row->ip_address,
                        $row->details,
                    ]);
                }

                fclose($output);
            }, 'audit_log_konverpro.csv', ['Content-Type' => 'text/csv']);
        }

        return $this->successResponse($query->paginate(50));
    }
}
