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
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:pdf|max:10240',
            'metadata' => 'required|array|min:1',
            'metadata.*.document_type' => 'required|string|max:255',
            'metadata.*.submission_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'metadata.*.municipal_offices' => 'nullable|string|max:255',
            'metadata.*.signatory_details' => 'nullable|string|max:500',
        ];
    }
}
