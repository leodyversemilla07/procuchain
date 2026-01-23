<?php

namespace App\Http\Requests\Procurement;

class BacResolutionDocumentRequest extends BaseProcurementRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->documentRules('bac_resolution_file'),
            'issuance_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'signatories' => 'required|array|min:1',
            'signatories.*.name' => 'required|string|min:1|max:255',
            'signatories.*.position' => 'required|string|min:1|max:255',
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
            ...$this->documentMessages('bac_resolution_file', 'BAC resolution file'),
            'issuance_date.date_format' => 'The issuance date must be in YYYY-MM-DD format.',
            'issuance_date.before_or_equal' => 'The issuance date cannot be in the future.',
            'signatories.required' => 'At least one signatory is required.',
            'signatories.array' => 'Signatories must be provided as an array.',
            'signatories.min' => 'At least one signatory is required.',
            'signatories.*.name.required' => 'Each signatory must have a name.',
            'signatories.*.position.required' => 'Each signatory must have a position.',
        ];
    }
}
