<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class PerformanceBondContractAndPoDocumentsRequest extends BaseProcurementRequest
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
            ...$this->documentRules('performance_bond_file', required: false),
            ...$this->documentRules('contract_file', required: false),
            ...$this->documentRules('po_file', required: false),
            'submission_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'bond_amount' => 'required|numeric|min:0',
            'signing_date' => 'required|date_format:Y-m-d|before_or_equal:today',
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
            ...$this->documentMessages('performance_bond_file', 'performance bond file'),
            ...$this->documentMessages('contract_file', 'contract file'),
            ...$this->documentMessages('po_file', 'purchase order file'),
            'bond_amount.numeric' => 'The bond amount must be a valid number.',
            'bond_amount.min' => 'The bond amount cannot be negative.',
        ];
    }
}
