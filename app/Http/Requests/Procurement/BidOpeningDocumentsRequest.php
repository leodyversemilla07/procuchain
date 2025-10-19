<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BidOpeningDocumentsRequest extends FormRequest
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
            'bid_documents' => 'required|array|min:1',
            'bid_documents.*' => 'required|file|mimes:pdf|max:10240',
            'bidders_data' => 'required|array|min:1',
            'bidders_data.*.bidder_name' => 'required|string|min:1|max:255',
            'bidders_data.*.bid_value' => 'required|numeric|min:0',
            'opening_date_time' => 'required|date_format:Y-m-d|before_or_equal:today',
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
            'bid_documents.*.max' => 'Each bid document must not exceed 10MB in size.',
            'bid_documents.*.mimes' => 'Only PDF files are allowed.',
            'bidders_data.*.bid_value.numeric' => 'The bid value must be a valid number.',
            'bidders_data.*.bid_value.min' => 'The bid value cannot be negative.',
        ];
    }
}
