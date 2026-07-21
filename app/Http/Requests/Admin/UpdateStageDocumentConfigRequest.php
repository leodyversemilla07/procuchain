<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStageDocumentConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string'],
            'optional_documents' => ['nullable', 'array'],
            'optional_documents.*' => ['string'],
        ];
    }
}
