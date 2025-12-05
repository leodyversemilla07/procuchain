<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Request validation for updating delivery details at the Notice to Proceed stage.
 *
 * Per NGPA IRR Section 71 (Contract Implementation), delivery details should be
 * specified at this stage before the contract is executed.
 */
class UpdateDeliveryDetailsRequest extends FormRequest
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
            'delivery_location' => 'required|string|min:5|max:255',
            'delivery_date' => 'required|date|after:today',
            'delivery_term_days' => 'required|integer|min:1|max:365',
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
            'delivery_location.required' => 'Delivery location is required.',
            'delivery_location.min' => 'Delivery location must be at least 5 characters.',
            'delivery_location.max' => 'Delivery location cannot exceed 255 characters.',

            'delivery_date.required' => 'Delivery date is required.',
            'delivery_date.date' => 'Delivery date must be a valid date.',
            'delivery_date.after' => 'Delivery date must be in the future.',

            'delivery_term_days.required' => 'Delivery term (in days) is required.',
            'delivery_term_days.integer' => 'Delivery term must be a whole number.',
            'delivery_term_days.min' => 'Delivery term must be at least 1 day.',
            'delivery_term_days.max' => 'Delivery term cannot exceed 365 days.',
        ];
    }
}
