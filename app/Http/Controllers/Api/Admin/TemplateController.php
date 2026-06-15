<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        
        // Header Style
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '031F37']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        // Sheet 1: Data Mahasiswa
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Mahasiswa');
        $headers1 = ['nim_asal', 'nama_lengkap', 'asal_kampus', 'asal_prodi', 'prodi_tujuan_unsia', 'email', 'no_whatsapp'];
        $sheet1->fromArray($headers1, NULL, 'A1');
        $sheet1->getStyle('A1:G1')->applyFromArray($headerStyle);

        // Add dummy data example
        $sheet1->setCellValue('A2', '2022001');
        $sheet1->setCellValue('B2', 'Budi Santoso');
        $sheet1->setCellValue('C2', 'Universitas Siber Asia');
        $sheet1->setCellValue('D2', 'Informatika');
        $sheet1->setCellValue('E2', 'PJJ Informatika');
        $sheet1->setCellValue('F2', 'budi@example.com');
        $sheet1->setCellValue('G2', '628123456789');

        // Sheet 2: Transkrip
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Transkrip');
        $headers2 = ['nim_asal', 'nama_mk_asal', 'sks_asal', 'nilai_huruf_asal', 'nilai_angka_asal'];
        $sheet2->fromArray($headers2, NULL, 'A1');
        $sheet2->getStyle('A1:E1')->applyFromArray($headerStyle);

        // Dummy transkrip for Budi
        $sheet2->setCellValue('A2', '2022001');
        $sheet2->setCellValue('B2', 'Algoritma Pemrograman');
        $sheet2->setCellValue('C2', '3');
        $sheet2->setCellValue('D2', 'A');
        $sheet2->setCellValue('E2', '4.0');
        
        $sheet2->setCellValue('A3', '2022001');
        $sheet2->setCellValue('B3', 'Basis Data');
        $sheet2->setCellValue('C3', '4');
        $sheet2->setCellValue('D3', 'B+');
        $sheet2->setCellValue('E3', '3.5');

        // Auto size columns
        foreach (range('A', 'G') as $col) { $sheet1->getColumnDimension($col)->setAutoSize(true); }
        foreach (range('A', 'E') as $col) { $sheet2->getColumnDimension($col)->setAutoSize(true); }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, 'Template_Konversi_UNSIA.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
