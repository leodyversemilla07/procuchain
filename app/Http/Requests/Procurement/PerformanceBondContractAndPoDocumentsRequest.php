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
            // Allow files to be nullable to match frontend behavior
            'performance_bond_file' => 'nullable|file|mimes:pdf|max:10240',
            'submission_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'bond_amount' => 'required|numeric|min:0|max:9999999999.99', // Changed to numeric validation
            // Allow files to be nullable to match frontend behavior
            'contract_file' => 'nullable|file|mimes:pdf|max:10240',
            // Allow files to be nullable to match frontend behavior
            'po_file' => 'nullable|file|mimes:pdf|max:10240',
            'signing_date' => 'required|date_format:Y-m-d|before_or_equal:today',
        ];
    }
}
