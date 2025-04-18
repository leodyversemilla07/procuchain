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
            'post_qualification_report' => 'required|file|mimes:pdf|max:10240',
            'twg_certification' => 'nullable|file|mimes:pdf|max:10240',
            'notice_of_post_qualification' => 'required|file|mimes:pdf|max:10240',
            'submission_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'outcome' => 'required|boolean',
            'remarks' => 'nullable|string|max:5000',
            'metadata' => 'required|array',
            'metadata.*.document_type' => 'required|string',
            'metadata.*.submission_date' => 'required|date_format:Y-m-d',
        ];
    }
}
