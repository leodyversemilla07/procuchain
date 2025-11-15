<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PerformanceBondContractAndPoDocumentsRequest extends FormRequest
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
            'performance_bond_file' => 'nullable|file|mimes:pdf|max:8192',
            'submission_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'bond_amount' => 'required|numeric|min:0',
            'contract_file' => 'nullable|file|mimes:pdf|max:8192',
            'po_file' => 'nullable|file|mimes:pdf|max:8192',
            'signing_date' => 'required|date_format:Y-m-d|before_or_equal:today',
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
            'performance_bond_file.max' => 'The performance bond file must not exceed 8MB in size.',
            'performance_bond_file.mimes' => 'Only PDF files are allowed.',
            'contract_file.max' => 'The contract file must not exceed 8MB in size.',
            'contract_file.mimes' => 'Only PDF files are allowed.',
            'po_file.max' => 'The purchase order file must not exceed 8MB in size.',
            'po_file.mimes' => 'Only PDF files are allowed.',
            'bond_amount.numeric' => 'The bond amount must be a valid number.',
            'bond_amount.min' => 'The bond amount cannot be negative.',
        ];
    }
}
