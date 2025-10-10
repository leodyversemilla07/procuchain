<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ProcurementInitiationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole(UserRoleEnums::BAC_SECRETARIAT->value);
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
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:pdf|max:10240',
            'metadata' => 'required|array|min:1',
            'metadata.*.document_type' => 'required|string|max:255',
            'metadata.*.submission_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'metadata.*.municipal_offices' => 'nullable|string|max:255',
            'metadata.*.signatories' => 'nullable|array',
            'metadata.*.signatories.*.name' => 'required|string|min:1|max:255',
            'metadata.*.signatories.*.position' => 'required|string|min:1|max:255',
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
            'files.*.max' => 'Each file must not exceed 10MB in size.',
            'files.*.mimes' => 'Only PDF files are allowed.',
            'metadata.*.submission_date.date_format' => 'The submission date must be in YYYY-MM-DD format.',
            'metadata.*.submission_date.before_or_equal' => 'The submission date cannot be in the future.',
            'metadata.*.signatories.array' => 'Signatories must be provided as an array.',
            'metadata.*.signatories.*.name.required' => 'Each signatory must have a name.',
            'metadata.*.signatories.*.position.required' => 'Each signatory must have a position.',
        ];
    }
}

