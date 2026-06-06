<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreKamusSinonimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user instanceof User && in_array($user->role->value, ['superadmin', 'akademik']);
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'kata_utama' => 'required|string|max:200',
            'sinonim' => 'required|string|max:200',
            'keterangan' => 'nullable|string|max:255',
        ];
    }
}
