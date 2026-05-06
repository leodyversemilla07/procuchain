<?php

namespace App\Http\Requests\Procurement;

use App\Http\Requests\Procurement\Traits\HasConferenceValidation;
use Illuminate\Contracts\Validation\ValidationRule;

class PreBidConferenceDocumentsRequest extends BaseProcurementRequest
{
    use HasConferenceValidation;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->conferenceRules(),
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
            ...$this->conferenceMessages('minutes file', 'attendance file'),
        ];
    }
}
