<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class NoticeToProceedDocumentRequest extends BaseProcurementRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->documentRules('ntp_file'),
            'issuance_date' => 'required|date_format:Y-m-d|before_or_equal:today',
        ];
    }

    /**
     * Get the custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            ...$this->documentMessages('ntp_file', 'notice to proceed file'),
        ];
    }
}
