<?php

namespace App\Http\Requests\Akademik;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAntreanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role->value === 'akademik';
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'nama_lengkap' => 'required|string|max:150',
            'nim_asal' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'no_whatsapp' => 'nullable|string|max:20',
            'asal_kampus' => 'nullable|string|max:150',
            'asal_prodi' => 'nullable|string|max:150',
            'id_prodi' => 'required|exists:prodi,id',
            'transkrip' => 'required|array',
            'transkrip.*.id' => 'required|exists:transkrip_asal,id',
            'transkrip.*.nama_mk_asal' => 'required|string|max:150',
            'transkrip.*.sks_asal' => 'required|integer|min:0',
            'transkrip.*.nilai_huruf_asal' => 'nullable|string|max:5',
        ];
    }
}
