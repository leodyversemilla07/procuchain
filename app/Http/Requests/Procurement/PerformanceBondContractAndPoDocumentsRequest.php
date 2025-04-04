<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\UserRoleEnums;
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
            'procurement_id'          => 'required|string|max:50',
            'procurement_title'       => 'required|string|min:5|max:255',
            'performance_bond_file'   => 'required|file|mimes:pdf|max:10240',
            'submission_date'         => 'required|date_format:Y-m-d|before_or_equal:today',
            'bond_amount'             => 'required|string|min:1|max:100',
            'contract_file'           => 'required|file|mimes:pdf|max:10240',
            'po_file'                 => 'required|file|mimes:pdf|max:10240',
            'signing_date'            => 'required|date_format:Y-m-d|before_or_equal:today',
        ];
    }
}
