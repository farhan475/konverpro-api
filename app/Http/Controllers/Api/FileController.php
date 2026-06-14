<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function showExcel(Pendaftar $pendaftar): StreamedResponse
    {
        // Check access if needed (e.g., admin who created it, or akademik/kaprodi)
        // For simplicity, allowing auth:sanctum users for now as they are all staff
        
        if (!$pendaftar->file_transkrip_excel_path) {
            abort(404, 'File not found.');
        }

        return Storage::disk('private')->download(
            $pendaftar->file_transkrip_excel_path,
            'Transkrip_' . $pendaftar->nama_lengkap . '.xlsx'
        );
    }

    public function showPdf(Pendaftar $pendaftar): StreamedResponse
    {
        if (!$pendaftar->file_transkrip_pdf_path) {
            abort(404, 'File not found.');
        }

        return Storage::disk('private')->download(
            $pendaftar->file_transkrip_pdf_path,
            'Transkrip_Original_' . $pendaftar->nama_lengkap . '.pdf'
        );
    }
}
