<?php

namespace App\Http\Requests\Kurikulum;

use Illuminate\Foundation\Http\FormRequest;

class ImportKurikulumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_prodi' => 'required|exists:prodi,id',
            'excel_data' => 'required|array',
            'excel_data.*.kode_mk' => 'required|string|max:20',
            'excel_data.*.nama_mk' => 'required|string|max:150',
            'excel_data.*.sks' => 'required|integer|min:1',
            'excel_data.*.semester' => 'required|integer|min:1',
            'excel_data.*.tipe_mk' => 'required|in:Wajib,Pilihan',
        ];
    }
}
