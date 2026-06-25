<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prodi;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController extends Controller
{
    public function download(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $prodiNames = [];
        foreach (Prodi::orderBy('nama_prodi')->pluck('nama_prodi') as $name) {
            if (is_string($name) && $name !== '') {
                $prodiNames[] = $name;
            }
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '031F37']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        // Sheet 1: Data Mahasiswa
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Mahasiswa');
        $headers1 = ['nim_asal', 'nama_lengkap', 'asal_kampus', 'asal_prodi', 'prodi_tujuan', 'email', 'no_whatsapp'];
        $sheet1->fromArray($headers1, null, 'A1');
        $sheet1->getStyle('A1:G1')->applyFromArray($headerStyle);
        $sheet1->freezePane('A2');

        $sheet1->setCellValue('I1', 'Panduan');
        $sheet1->setCellValue('I2', 'Jangan mengubah nama header baris 1.');
        $sheet1->setCellValue('I3', 'Kolom prodi_tujuan harus persis sama dengan nama prodi UNSIA.');
        $sheet1->setCellValue('I4', 'Nomor WhatsApp gunakan format 628xxx.');
        $sheet1->setCellValue('I5', 'Lihat sheet Contoh untuk contoh pengisian.');
        $sheet1->setAutoFilter('A1:G1');

        // Sheet 2: Transkrip
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Transkrip');
        $headers2 = ['nim_asal', 'nama_mk_asal', 'sks_asal', 'nilai_huruf_asal', 'nilai_angka_asal'];
        $sheet2->fromArray($headers2, null, 'A1');
        $sheet2->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet2->freezePane('A2');

        $sheet2->setAutoFilter('A1:E1');

        // Sheet 3: Referensi, digunakan untuk dropdown agar input lebih rapi.
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Referensi');
        $sheet3->setCellValue('A1', 'prodi_tujuan');
        $prodiRows = [];
        foreach ($prodiNames as $name) {
            $prodiRows[] = [$name];
        }
        $sheet3->fromArray($prodiRows, null, 'A2');
        $sheet3->getStyle('A1')->applyFromArray($headerStyle);

        if (count($prodiNames) > 0) {
            $spreadsheet->addNamedRange(new NamedRange(
                'DaftarProdi',
                $sheet3,
                '$A$2:$A$'.(count($prodiNames) + 1)
            ));
            for ($row = 2; $row <= 500; $row++) {
                $validation = $sheet1->getCell("E{$row}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Program studi tidak valid');
                $validation->setError('Pilih program studi dari daftar yang tersedia.');
                $validation->setFormula1('=DaftarProdi');
            }
        }
        $sheet3->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        for ($row = 2; $row <= 1000; $row++) {
            $gradeValidation = $sheet2->getCell("D{$row}")->getDataValidation();
            $gradeValidation->setType(DataValidation::TYPE_LIST);
            $gradeValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $gradeValidation->setAllowBlank(false);
            $gradeValidation->setShowDropDown(true);
            $gradeValidation->setShowErrorMessage(true);
            $gradeValidation->setFormula1('"A,B+,B,C+,C,D,E"');

            $sksValidation = $sheet2->getCell("C{$row}")->getDataValidation();
            $sksValidation->setType(DataValidation::TYPE_WHOLE);
            $sksValidation->setOperator(DataValidation::OPERATOR_BETWEEN);
            $sksValidation->setAllowBlank(false);
            $sksValidation->setShowErrorMessage(true);
            $sksValidation->setFormula1('1');
            $sksValidation->setFormula2('24');
        }

        $exampleSheet = $spreadsheet->createSheet();
        $exampleSheet->setTitle('Contoh');
        $exampleSheet->setCellValue('A1', 'Contoh Sheet Mahasiswa');
        $exampleSheet->setCellValue('A2', 'nim_asal');
        $exampleSheet->setCellValue('B2', 'nama_lengkap');
        $exampleSheet->setCellValue('C2', 'asal_kampus');
        $exampleSheet->setCellValue('D2', 'asal_prodi');
        $exampleSheet->setCellValue('E2', 'prodi_tujuan');
        $exampleSheet->setCellValue('F2', 'email');
        $exampleSheet->setCellValue('G2', 'no_whatsapp');
        $exampleSheet->fromArray(
            ['2022001', 'Budi Santoso', 'Universitas Contoh', 'Informatika', 'PJJ Informatika', 'budi@example.com', '628123456789'],
            null,
            'A3'
        );
        $exampleSheet->setCellValue('A6', 'Contoh Sheet Transkrip');
        $exampleSheet->fromArray(
            ['nim_asal', 'nama_mk_asal', 'sks_asal', 'nilai_huruf_asal', 'nilai_angka_asal'],
            null,
            'A7'
        );
        $exampleSheet->fromArray(['2022001', 'Algoritma Pemrograman', 3, 'A', 4.0], null, 'A8');
        $exampleSheet->fromArray(['2022001', 'Basis Data', 4, 'B+', 3.5], null, 'A9');
        $exampleSheet->getStyle('A2:G2')->applyFromArray($headerStyle);
        $exampleSheet->getStyle('A7:E7')->applyFromArray($headerStyle);

        // Auto size columns
        foreach (range('A', 'G') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }
        foreach (range('I', 'I') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }
        foreach (range('A', 'E') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }
        foreach (range('A', 'A') as $col) {
            $sheet3->getColumnDimension($col)->setAutoSize(true);
        }
        foreach (range('A', 'G') as $col) {
            $exampleSheet->getColumnDimension($col)->setAutoSize(true);
        }
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'Template_Konversi_UNSIA.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
