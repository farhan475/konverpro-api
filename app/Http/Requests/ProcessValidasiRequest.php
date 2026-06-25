<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ProcessValidasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role->value === 'kaprodi';
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'id_mk_tujuan' => 'nullable|exists:kurikulum_mk,id',
            'nilai_akhir_huruf' => 'nullable|string|max:5',
            'sks_diakui' => 'nullable|integer|min:0',
        ];
    }
}
