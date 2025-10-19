<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BiddingDocumentsRequest extends FormRequest
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
            'bidding_document_file' => 'required|file|mimes:pdf|max:10240',
            'issuance_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'validity_period_start' => 'required|date_format:Y-m-d|before_or_equal:validity_period_end',
            'validity_period_end' => 'required|date_format:Y-m-d|after:validity_period_start',
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
            'bidding_document_file.max' => 'The bidding documents file must not exceed 10MB in size.',
            'bidding_document_file.mimes' => 'Only PDF files are allowed.',
            'validity_period_start.before_or_equal' => 'The validity period start date must be before or equal to the end date.',
            'validity_period_end.after' => 'The validity period end date must be after the start date.',
        ];
    }
}
