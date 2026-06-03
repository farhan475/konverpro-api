<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransaksiSaldo;
use App\Models\Kampus;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    public function index(Request $request)
    {
        $transaksi = TransaksiSaldo::with('kampus')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transaksi
        ]);
    }

    public function approve($id)
    {
        return DB::transaction(function () use ($id) {
            $transaksi = TransaksiSaldo::findOrFail($id);
            if ($transaksi->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Transaksi sudah diproses sebelumnya.'], 400);
            }

            if ($transaksi->jenis_transaksi === 'topup') {
                $kampus = Kampus::findOrFail($transaksi->id_kampus);
                $kampus->increment('saldo_aktif', $transaksi->nominal);
            }

            $transaksi->update(['status' => 'success']);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disetujui dan saldo telah diperbarui.'
            ]);
        });
    }

    public function reject(Request $request, $id)
    {
        $transaksi = TransaksiSaldo::findOrFail($id);
        if ($transaksi->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Transaksi sudah diproses sebelumnya.'], 400);
        }

        $transaksi->update([
            'status' => 'failed',
            'catatan_admin' => $request->catatan_admin
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi telah ditolak.'
        ]);
    }
}
