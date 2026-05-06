<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class PostQualificationDocumentsRequest extends BaseProcurementRequest
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
            ...$this->documentRules('post_qualification_report'),
            ...$this->documentRules('twg_certification', required: false),
            ...$this->documentRules('notice_of_post_qualification'),
            'submission_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'outcome' => 'required|boolean',
            'remarks' => 'nullable|string|max:1000',
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
            ...$this->documentMessages('post_qualification_report', 'post qualification report'),
            ...$this->documentMessages('twg_certification', 'TWG certification'),
            ...$this->documentMessages('notice_of_post_qualification', 'notice of post qualification'),
        ];
    }
}
