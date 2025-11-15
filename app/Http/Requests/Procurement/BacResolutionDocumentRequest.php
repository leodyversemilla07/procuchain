<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BacResolutionDocumentRequest extends FormRequest
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
            'pr_number' => 'required|string|max:50',
            'procurement_title' => 'required|string|min:5|max:255',
            'bac_resolution_file' => 'required|file|mimes:pdf|max:8192',
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
            'bac_resolution_file.max' => 'The BAC resolution file must not exceed 8MB in size.',
            'bac_resolution_file.mimes' => 'Only PDF files are allowed.',
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
