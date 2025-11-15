<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class MonitoringDocumentRequest extends FormRequest
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
            'compliance_file' => 'required|file|mimes:pdf|max:8192',
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
            'compliance_file.max' => 'The compliance file must not exceed 8MB in size.',
            'compliance_file.mimes' => 'Only PDF files are allowed.',
        ];
    }
}
