<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse|StreamedResponse
    {
        if ($request->query('format') === 'csv') {
            return $this->downloadCsv();
        }

        $stats = [
            'global' => [
                'total_pendaftar' => Pendaftar::count(),
                'total_sks_diakui' => Pendaftar::where('status', 'Approved')->sum('total_sks_diakui'),
                'avg_sks_per_mhs' => round((float) (Pendaftar::where('status', 'Approved')->avg('total_sks_diakui') ?? 0), 2),
            ],
            'by_status' => Pendaftar::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get(),
            'by_prodi' => Prodi::withCount('pendaftar')
                ->with(['pendaftar' => function ($query) {
                    $query->select('id_prodi', 'status', DB::raw('count(*) as count'), DB::raw('sum(total_sks_diakui) as sks'))
                        ->groupBy('id_prodi', 'status');
                }])
                ->get()
                ->map(function ($prodi) {
                    return [
                        'nama_prodi' => $prodi->nama_prodi,
                        'total_mhs' => $prodi->pendaftar_count,
                        'approved' => $prodi->pendaftar->where('status', 'Approved')->sum('count'),
                        'total_sks' => $prodi->pendaftar->where('status', 'Approved')->sum('sks'),
                    ];
                }),
            'monthly_trends' => $this->monthlyTrends(),
        ];

        return $this->successResponse($stats);
    }

    /** @return array<int, array{month: string, total: int}> */
    private function monthlyTrends(): array
    {
        $counts = [];

        foreach (Pendaftar::query()->latest()->get(['created_at']) as $pendaftar) {
            $createdAt = $pendaftar->created_at;
            $month = $createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m') : '-';
            $counts[$month] = ($counts[$month] ?? 0) + 1;
        }

        $trends = [];
        foreach (array_slice($counts, 0, 6, true) as $month => $total) {
            $trends[] = ['month' => (string) $month, 'total' => (int) $total];
        }

        return $trends;
    }

    private function downloadCsv(): StreamedResponse
    {
        $rows = Pendaftar::with('prodi')->latest()->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fputcsv($output, ['Nama Mahasiswa', 'NIM Asal', 'Prodi', 'Status', 'Asal Kampus', 'Asal Prodi', 'Total SKS Diakui', 'Tanggal Input']);

            foreach ($rows as $row) {
                $createdAt = $row->created_at;
                fputcsv($output, [
                    $row->nama_lengkap,
                    $row->nim_asal,
                    $row->prodi?->nama_prodi,
                    $row->status->value,
                    $row->asal_kampus,
                    $row->asal_prodi,
                    $row->total_sks_diakui,
                    $createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($output);
        }, 'laporan_global_konverpro.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
