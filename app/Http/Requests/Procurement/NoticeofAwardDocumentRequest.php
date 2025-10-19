<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class NoticeOfAwardDocumentRequest extends FormRequest
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
            'noa_file' => 'required|file|mimes:pdf|max:10240',
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
            'noa_file.max' => 'The notice of award file must not exceed 10MB in size.',
            'noa_file.mimes' => 'Only PDF files are allowed.',
            'signatories.required' => 'At least one signatory is required.',
            'signatories.array' => 'Signatories must be provided as an array.',
            'signatories.min' => 'At least one signatory is required.',
            'signatories.*.name.required' => 'Each signatory must have a name.',
            'signatories.*.position.required' => 'Each signatory must have a position.',
        ];
    }
}
