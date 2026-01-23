<?php

namespace App\Http\Requests\Procurement;

class SupplementalBidBulletinDecisionRequest extends BaseProcurementRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            'supplemental_bid_needed' => 'required|boolean',
        ];
    }
}
