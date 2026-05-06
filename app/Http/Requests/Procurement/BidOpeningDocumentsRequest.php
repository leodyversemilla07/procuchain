<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class BidOpeningDocumentsRequest extends BaseProcurementRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            ...$this->multipleDocumentRules('bid_documents'),
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
            ...$this->commonMessages(),
            ...$this->documentMessages('bid_documents'),
            'bidders_data.*.bid_value.numeric' => 'The bid value must be a valid number.',
            'bidders_data.*.bid_value.min' => 'The bid value cannot be negative.',
        ];
    }
}
