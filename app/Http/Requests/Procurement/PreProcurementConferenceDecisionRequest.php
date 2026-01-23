<?php

namespace App\Http\Requests\Procurement;

class PreProcurementConferenceDecisionRequest extends BaseProcurementRequest
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
            'conference_held' => 'required|boolean',
        ];
    }
}
