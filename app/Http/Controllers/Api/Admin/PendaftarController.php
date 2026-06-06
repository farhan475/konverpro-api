<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\User;
use App\Services\ExcelParserService;
use App\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PendaftarController extends Controller
{
    use ApiResponse;

    public function __construct(protected ExcelParserService $excelParser) {}

    public function index(): JsonResponse
    {
        return $this->successResponse(
            Pendaftar::where('created_by', auth()->id())
                ->with('prodi')
                ->latest()
                ->paginate(20)
        );
    }

    public function store(\App\Http\Requests\StorePendaftarRequest $request): JsonResponse
    {
        $fileExcel = $request->file('file_excel');
        $filePdf = $request->file('file_pdf');

        if (!$fileExcel) {
            return $this->errorResponse('Excel file is required.', 400);
        }

        $pathExcel = $fileExcel->store('pendaftar/excel', 'private');
        $pathPdf = $filePdf ? $filePdf->store('pendaftar/pdf', 'private') : null;

        if (!$pathExcel) {
            return $this->errorResponse('Failed to store Excel file.', 500);
        }

        $fullPathExcel = Storage::disk('private')->path($pathExcel);
        
        try {
            /** @var User $user */
            $user = auth()->user();
            $pendaftars = $this->excelParser->parse($fullPathExcel, (string) $user->id);
            
            foreach ($pendaftars as $p) {
                if ($pathPdf) {
                    $p->update(['file_transkrip_pdf_path' => $pathPdf]);
                }
                $p->update(['file_transkrip_excel_path' => $pathExcel]);
            }

            AuditService::log('upload_pendaftar', 'Pendaftar', null, "Uploaded Excel with " . count($pendaftars) . " students");

            return $this->successResponse($pendaftars, count($pendaftars) . ' students processed successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Error parsing Excel: ' . $e->getMessage(), 500);
        }
    }

    public function show(Pendaftar $pendaftar): JsonResponse
    {
        if ($pendaftar->created_by !== auth()->id()) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        return $this->successResponse($pendaftar->load('prodi', 'transkripAsal', 'hasilKonversi.mkTujuan'));
    }
}
