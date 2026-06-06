<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        
        // Sheet 1: Data Mahasiswa
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Mahasiswa');
        $sheet1->setCellValue('A1', 'nim_asal');
        $sheet1->setCellValue('B1', 'nama_lengkap');
        $sheet1->setCellValue('C1', 'asal_kampus');
        $sheet1->setCellValue('D1', 'asal_prodi');
        $sheet1->setCellValue('E1', 'prodi_tujuan_unsia');
        $sheet1->setCellValue('F1', 'email');
        $sheet1->setCellValue('G1', 'no_whatsapp');

        // Add dummy data example
        $sheet1->setCellValue('A2', '2022001');
        $sheet1->setCellValue('B2', 'Budi Santoso');
        $sheet1->setCellValue('C2', 'Universitas Contoh');
        $sheet1->setCellValue('D2', 'Informatika');
        $sheet1->setCellValue('E2', 'PJJ Informatika');
        $sheet1->setCellValue('F2', 'budi@example.com');
        $sheet1->setCellValue('G2', '08123456789');

        // Sheet 2: Transkrip
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Transkrip');
        $sheet2->setCellValue('A1', 'nim_asal');
        $sheet2->setCellValue('B1', 'nama_mk_asal');
        $sheet2->setCellValue('C1', 'sks_asal');
        $sheet2->setCellValue('D1', 'nilai_huruf_asal');
        $sheet2->setCellValue('E1', 'nilai_angka_asal');

        // Dummy transkrip for Budi
        $sheet2->setCellValue('A2', '2022001');
        $sheet2->setCellValue('B2', 'Algoritma');
        $sheet2->setCellValue('C2', '3');
        $sheet2->setCellValue('D2', 'A');
        
        $sheet2->setCellValue('A3', '2022001');
        $sheet2->setCellValue('B3', 'Basis Data');
        $sheet2->setCellValue('C3', '4');
        $sheet2->setCellValue('D3', 'B+');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, 'Template_Konversi_UNSIA.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
