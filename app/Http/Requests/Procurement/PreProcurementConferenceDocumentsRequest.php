<?php

namespace App\Http\Requests\Procurement;

class PreProcurementConferenceDocumentsRequest extends BaseProcurementRequest
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
            ...$this->documentRules('minutes_file'),
            ...$this->documentRules('attendance_file'),
            'meeting_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'participants' => 'required|array|min:1',
            'participants.*.name' => 'required|string|min:1|max:255',
            'participants.*.organization' => 'required|string|min:1|max:255',
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
            ...$this->documentMessages('minutes_file'),
            ...$this->documentMessages('attendance_file'),
            'participants.required' => 'At least one participant is required.',
            'participants.array' => 'Participants must be provided as an array.',
            'participants.min' => 'At least one participant is required.',
            'participants.*.name.required' => 'Each participant must have a name.',
            'participants.*.organization.required' => 'Each participant must have an organization.',
        ];
    }
}
