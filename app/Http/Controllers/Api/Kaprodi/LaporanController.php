<?php

namespace App\Http\Controllers\Api\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse|StreamedResponse
    {
        $prodiIds = Prodi::forCurrentKaprodi()->pluck('id');
        $approvedQuery = Pendaftar::whereIn('id_prodi', $prodiIds)
            ->where('status', 'Approved')
            ->with('prodi')
            ->latest();

        if ($request->query('format') === 'csv') {
            return $this->downloadCsv($approvedQuery->get());
        }

        $stats = [
            'summary' => Pendaftar::whereIn('id_prodi', $prodiIds)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
            'total_sks' => Pendaftar::whereIn('id_prodi', $prodiIds)
                ->where('status', 'Approved')
                ->sum('total_sks_diakui'),
            'total_pendaftar' => Pendaftar::whereIn('id_prodi', $prodiIds)->count(),
            'recent_approved' => $approvedQuery
                ->limit(20)
                ->get(),
        ];

        return $this->successResponse($stats);
    }

    /** @param Collection<int, Pendaftar> $rows */
    private function downloadCsv(Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fputcsv($output, ['Nama Mahasiswa', 'NIM Asal', 'Prodi', 'Asal Kampus', 'Asal Prodi', 'Total SKS Diakui', 'Tanggal Approval']);

            foreach ($rows as $row) {
                $updatedAt = $row->updated_at;
                fputcsv($output, [
                    $row->nama_lengkap,
                    $row->nim_asal,
                    $row->prodi?->nama_prodi,
                    $row->asal_kampus,
                    $row->asal_prodi,
                    $row->total_sks_diakui,
                    $updatedAt instanceof \DateTimeInterface ? $updatedAt->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($output);
        }, 'laporan_kaprodi_konverpro.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
