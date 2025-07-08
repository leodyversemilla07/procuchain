<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PreBidConferenceDocumentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === UserRoleEnums::BAC_SECRETARIAT->value;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'procurement_id' => 'required|string|max:50',
            'procurement_title' => 'required|string|min:5|max:255',
            'minutes_file' => 'required|file|mimes:pdf|max:10240',
            'attendance_file' => 'required|file|mimes:pdf|max:10240',
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
            'minutes_file.max' => 'The minutes file must not exceed 10MB in size.',
            'minutes_file.mimes' => 'Only PDF files are allowed.',
            'attendance_file.max' => 'The attendance file must not exceed 10MB in size.',
            'attendance_file.mimes' => 'Only PDF files are allowed.',
            'participants.required' => 'At least one participant is required.',
            'participants.array' => 'Participants must be provided as an array.',
            'participants.min' => 'At least one participant is required.',
            'participants.*.name.required' => 'Each participant must have a name.',
            'participants.*.organization.required' => 'Each participant must have an organization.',
        ];
    }
}
