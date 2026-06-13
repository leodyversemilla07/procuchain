<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class SupplementalBidBulletinDocumentsRequest extends BaseProcurementRequest
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
            ...$this->documentRules('bulletin_File'),
            'bulletin_number' => 'required|string|min:1|max:50',
            'bulletin_title' => 'required|string|min:5|max:255',
            'issue_date' => 'required|date_format:Y-m-d|before_or_equal:today',
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
            ...$this->documentMessages('bulletin_File', 'bulletin File'),
        ];
    }
}
