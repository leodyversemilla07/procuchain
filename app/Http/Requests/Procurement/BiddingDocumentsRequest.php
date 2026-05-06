<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class BiddingDocumentsRequest extends BaseProcurementRequest
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
            ...$this->documentRules('bidding_document_file'),
            'issuance_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'validity_period_start' => 'required|date_format:Y-m-d|before_or_equal:validity_period_end',
            'validity_period_end' => 'required|date_format:Y-m-d|after:validity_period_start',
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
            ...$this->documentMessages('bidding_document_file', 'bidding documents file'),
            'validity_period_start.before_or_equal' => 'The validity period start date must be before or equal to the end date.',
            'validity_period_end.after' => 'The validity period end date must be after the start date.',
        ];
    }
}
