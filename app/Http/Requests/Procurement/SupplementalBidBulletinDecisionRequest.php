<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Contracts\Validation\ValidationRule;

class SupplementalBidBulletinDecisionRequest extends BaseProcurementRequest
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
            'supplemental_bid_needed' => 'required|boolean',
        ];
    }
}
