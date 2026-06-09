<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePendaftarRequest;
use App\Models\Pendaftar;
use App\Services\AuditService;
use App\Services\ExcelParserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PendaftarController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ExcelParserService $excelParser,
        private AuditService $audit
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = Pendaftar::where('created_by', $request->user()->id)
            ->with('prodi:id,nama_prodi,kode_prodi')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', "%{$request->search}%")
                  ->orWhere('nim_asal', 'like', "%{$request->search}%");
            })
            ->latest()
            ->paginate(20);

        return $this->successResponse($data);
    }

    public function store(StorePendaftarRequest $request): JsonResponse
    {
        $fileExcel = $request->file('file_excel');
        $filePdf   = $request->file('file_pdf');

        $pathExcel = $fileExcel->store('pendaftar/excel', 'private');
        $pathPdf   = $filePdf?->store('pendaftar/pdf', 'private');

        if (!$pathExcel) {
            return $this->errorResponse('Gagal menyimpan file Excel.', 500);
        }

        try {
            $fullPath   = Storage::disk('private')->path($pathExcel);
            $pendaftars = $this->excelParser->parse($fullPath, (string) $request->user()->id);

            foreach ($pendaftars as $p) {
                $p->update([
                    'file_transkrip_excel_path' => $pathExcel,
                    'file_transkrip_pdf_path'   => $pathPdf,
                ]);
            }

            $this->audit->log(
                'pendaftar.upload',
                'Pendaftar',
                null,
                count($pendaftars) . ' mahasiswa diimport dari Excel'
            );

            return $this->createdResponse(
                ['jumlah' => count($pendaftars)],
                count($pendaftars) . ' data pendaftar berhasil diimport.'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memproses file Excel: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->created_by !== $request->user()->id) {
            return $this->unauthorizedResponse();
        }

        return $this->successResponse(
            $pendaftar->load(['prodi:id,nama_prodi', 'transkripAsal', 'hasilKonversi.mkTujuan'])
        );
    }
}