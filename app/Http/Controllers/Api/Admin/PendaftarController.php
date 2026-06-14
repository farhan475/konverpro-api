<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePendaftarRequest;
use App\Models\Pendaftar;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ExcelParserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PendaftarController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExcelParserService $excelParser,
        private AuditService $audit
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            Pendaftar::where('created_by', Auth::id())
                ->with('prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StorePendaftarRequest $request): JsonResponse
    {
        $fileExcel = $request->file('file_excel');
        $filePdf = $request->file('file_pdf');

        if (!$fileExcel) {
            return $this->errorResponse('File Excel wajib diunggah.', 400);
        }

        $disk = config('filesystems.default', 'local');
        $tmpPath = $fileExcel->storeAs(
            'tmp/pendaftar',
            Str::uuid() . '.' . $fileExcel->getClientOriginalExtension(),
            'local'
        );

        if (!$tmpPath) {
            return $this->errorResponse('Gagal menyimpan file Excel sementara.', 500);
        }

        $fullPathExcel = Storage::disk('local')->path($tmpPath);

        try {
            /** @var User $user */
            $user = Auth::user();
            $pendaftars = $this->excelParser->parse($fullPathExcel, (string) $user->id);

            if (count($pendaftars) === 0) {
                return $this->errorResponse('Tidak ada data mahasiswa valid yang dapat diproses dari Excel.', 422);
            }

            $pathExcel = $fileExcel->store('pendaftar/excel', $disk);
            $pathPdf = $filePdf ? $filePdf->store('pendaftar/pdf', $disk) : null;

            if (!$pathExcel) {
                return $this->errorResponse('Gagal mengunggah file Excel ke storage.', 500);
            }

            foreach ($pendaftars as $pendaftar) {
                $pendaftar->update([
                    'file_transkrip_excel_path' => $pathExcel,
                    'file_transkrip_pdf_path' => $pathPdf,
                ]);
            }

            $this->audit->log(
                'upload_pendaftar',
                'Pendaftar',
                null,
                'Uploaded Excel with ' . count($pendaftars) . ' students to disk: ' . $disk
            );

            return $this->successResponse(
                $pendaftars,
                count($pendaftars) . ' mahasiswa berhasil diproses.'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse('Gagal memproses Excel: ' . $e->getMessage(), 500);
        } finally {
            Storage::disk('local')->delete($tmpPath);
        }
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->created_by !== Auth::id()) {
            return $this->errorResponse('Tidak memiliki akses.', 403);
        }

        return $this->successResponse($pendaftar->load('prodi', 'transkripAsal', 'hasilKonversi.mkTujuan'));
    }
}
