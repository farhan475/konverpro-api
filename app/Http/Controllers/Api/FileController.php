<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Pendaftar;
use App\Models\Prodi;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function showTandaTangan(string $userId): StreamedResponse|JsonResponse
    {
        $currentUser = auth()->user();

        // Only the user themselves or superadmin can access signature
        if ($currentUser->id !== $userId && $currentUser->role !== RoleEnum::SUPERADMIN) {
            return $this->unauthorizedResponse('Akses ditolak.');
        }

        $user = User::find($userId);
        if (! $user?->tanda_tangan_path || ! Storage::disk('private')->exists($user->tanda_tangan_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('private')->response($user->tanda_tangan_path);
    }

    public function showExcel(Pendaftar $pendaftar): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Security Check for Admin
        if ($user->role === RoleEnum::ADMIN && $pendaftar->created_by !== $user->id) {
            return $this->unauthorizedResponse('Unauthorized. Berkas ini bukan milik Anda.');
        }

        // Security Check for Kaprodi
        if ($user->role === RoleEnum::KAPRODI) {
            $prodiIds = Prodi::where('id_kaprodi', $user->id)->pluck('id');
            if (! $prodiIds->contains($pendaftar->id_prodi)) {
                return $this->unauthorizedResponse('Unauthorized. Pendaftar bukan dari prodi Anda.');
            }
        }

        if (! $pendaftar->file_transkrip_excel_path) {
            abort(404, 'File not found.');
        }

        $this->audit->log('file.excel_downloaded', 'Pendaftar', $pendaftar->id);

        return Storage::disk('private')->download(
            $pendaftar->file_transkrip_excel_path,
            'Transkrip_'.$pendaftar->nama_lengkap.'.xlsx'
        );
    }

    public function showPdf(Pendaftar $pendaftar): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        // Security Check for Admin
        if ($user->role === RoleEnum::ADMIN && $pendaftar->created_by !== $user->id) {
            return $this->unauthorizedResponse('Unauthorized. Berkas ini bukan milik Anda.');
        }

        // Security Check for Kaprodi
        if ($user->role === RoleEnum::KAPRODI) {
            $prodiIds = Prodi::where('id_kaprodi', $user->id)->pluck('id');
            if (! $prodiIds->contains($pendaftar->id_prodi)) {
                return $this->unauthorizedResponse('Unauthorized. Pendaftar bukan dari prodi Anda.');
            }
        }

        if (! $pendaftar->file_transkrip_pdf_path) {
            abort(404, 'File not found.');
        }

        $this->audit->log('file.pdf_downloaded', 'Pendaftar', $pendaftar->id);

        return Storage::disk('private')->download(
            $pendaftar->file_transkrip_pdf_path,
            'Transkrip_Original_'.$pendaftar->nama_lengkap.'.pdf'
        );
    }
}
