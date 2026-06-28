<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePendaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role->value === 'admin';
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'file_excel' => 'required|file|mimes:xlsx,xls|max:10240',
            'file_pdf' => 'nullable|file|mimes:pdf',
        ];
    }
}
