<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class BidEvaluationDocumentsRequest extends BaseProcurementRequest
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
            ...$this->documentRules('summary_File'),
            ...$this->documentRules('abstract_File'),
            'evaluation_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'evaluators' => 'required|array|min:1',
            'evaluators.*.name' => 'required|string|min:1|max:255',
            'evaluators.*.position' => 'required|string|min:1|max:255',
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
            ...$this->documentMessages('summary_File', 'summary File'),
            ...$this->documentMessages('abstract_File', 'abstract File'),
            'evaluators.required' => 'At least one evaluator is required.',
            'evaluators.array' => 'Evaluators must be provided as an array.',
            'evaluators.min' => 'At least one evaluator is required.',
            'evaluators.*.name.required' => 'Each evaluator must have a name.',
            'evaluators.*.position.required' => 'Each evaluator must have a position.',
        ];
    }
}
