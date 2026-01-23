<?php

namespace App\Http\Requests\Procurement;

class MonitoringDocumentRequest extends BaseProcurementRequest
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
            ...$this->documentRules('compliance_file'),
            'report_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'report_notes' => 'required|string|min:5|max:1000',
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
            ...$this->documentMessages('compliance_file', 'compliance file'),
        ];
    }
}
