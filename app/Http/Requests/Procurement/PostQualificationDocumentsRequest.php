<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PostQualificationDocumentsRequest extends FormRequest
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
            'post_qualification_report' => 'required|file|mimes:pdf|max:51200',
            'twg_certification' => 'nullable|file|mimes:pdf|max:51200',
            'notice_of_post_qualification' => 'required|file|mimes:pdf|max:51200',
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
            'post_qualification_report.max' => 'The post qualification report must not exceed 50MB in size.',
            'post_qualification_report.mimes' => 'Only PDF files are allowed.',
            'twg_certification.max' => 'The TWG certification must not exceed 50MB in size.',
            'twg_certification.mimes' => 'Only PDF files are allowed.',
            'notice_of_post_qualification.max' => 'The notice of post qualification must not exceed 50MB in size.',
            'notice_of_post_qualification.mimes' => 'Only PDF files are allowed.',
        ];
    }
}
