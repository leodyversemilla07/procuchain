<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class CorrectProcurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has permission to manage procurements (which includes corrections)
        return auth()->user()?->canManageProcurement() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'correction_reason' => 'required|string|min:10|max:1000',

            // Basic procurement information
            'title' => 'sometimes|required|string|min:5|max:255',
            'description' => 'sometimes|required|string|min:10|max:2000',
            'abc_amount' => 'sometimes|required|numeric|min:0|max:999999999.99',
            'funding_source' => 'sometimes|required|string|min:2|max:255',
            'category' => 'sometimes|required|string|in:goods,services,works',
            'procurement_mode' => 'sometimes|required|string|in:shopping,small_value_procurement,competitive_bidding,limited_source_bidding,direct_contracting,repeat_order,shopping_two_failed_biddings,negotiated_procurement',

            // Office and organizational details
            'office' => 'sometimes|required|string|min:2|max:255',
            'end_user' => 'sometimes|nullable|string|min:2|max:255',
            'purpose' => 'sometimes|required|string|min:10|max:1000',

            // Delivery information
            'delivery_location' => 'sometimes|required|string|min:2|max:255',
            'delivery_date' => 'sometimes|required|date|after:today',
            'delivery_term_days' => 'sometimes|required|integer|min:1|max:365',

            // BAC Resolution (conditional validation)
            'bac_resolution_number' => 'sometimes|nullable|string|min:2|max:100',
            'bac_resolution_date' => 'sometimes|nullable|date|before_or_equal:today',

            // PhilGEPS (conditional validation)
            'philgeps_reference' => 'sometimes|nullable|string|min:2|max:100',
            'philgeps_posting_date' => 'sometimes|nullable|date|before_or_equal:today',

            // Approval information (restricted permissions)
            'approved_by' => 'sometimes|nullable|string|min:2|max:255',
            'approval_date' => 'sometimes|nullable|date|before_or_equal:today',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'correction_reason.required' => 'Please provide a reason for this correction.',
            'correction_reason.min' => 'The correction reason must be at least 10 characters.',
            'correction_reason.max' => 'The correction reason cannot exceed 1000 characters.',

            'title.min' => 'The procurement title must be at least 5 characters.',
            'title.max' => 'The procurement title cannot exceed 255 characters.',

            'description.min' => 'The description must be at least 10 characters.',
            'description.max' => 'The description cannot exceed 2000 characters.',

            'abc_amount.numeric' => 'The ABC amount must be a valid number.',
            'abc_amount.min' => 'The ABC amount must be greater than 0.',
            'abc_amount.max' => 'The ABC amount cannot exceed ₱999,999,999.99.',

            'funding_source.min' => 'The funding source must be at least 2 characters.',
            'funding_source.max' => 'The funding source cannot exceed 255 characters.',

            'category.in' => 'Please select a valid procurement category.',

            'procurement_mode.in' => 'Please select a valid procurement mode.',

            'office.min' => 'The office must be at least 2 characters.',
            'office.max' => 'The office cannot exceed 255 characters.',

            'end_user.min' => 'The end user must be at least 2 characters.',
            'end_user.max' => 'The end user cannot exceed 255 characters.',

            'purpose.min' => 'The purpose must be at least 10 characters.',
            'purpose.max' => 'The purpose cannot exceed 1000 characters.',

            'delivery_location.min' => 'The delivery location must be at least 2 characters.',
            'delivery_location.max' => 'The delivery location cannot exceed 255 characters.',

            'delivery_date.after' => 'The delivery date must be in the future.',
            'delivery_term_days.min' => 'Delivery term must be at least 1 day.',
            'delivery_term_days.max' => 'Delivery term cannot exceed 365 days.',

            'bac_resolution_number.min' => 'BAC resolution number must be at least 2 characters.',
            'bac_resolution_number.max' => 'BAC resolution number cannot exceed 100 characters.',

            'bac_resolution_date.before_or_equal' => 'BAC resolution date cannot be in the future.',

            'philgeps_reference.min' => 'PhilGEPS reference must be at least 2 characters.',
            'philgeps_reference.max' => 'PhilGEPS reference cannot exceed 100 characters.',

            'philgeps_posting_date.before_or_equal' => 'PhilGEPS posting date cannot be in the future.',

            'approved_by.min' => 'Approved by must be at least 2 characters.',
            'approved_by.max' => 'Approved by cannot exceed 255 characters.',

            'approval_date.before_or_equal' => 'Approval date cannot be in the future.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            // Check if at least one field is being corrected
            $correctableFields = [
                'title', 'description', 'abc_amount', 'funding_source', 'category', 'procurement_mode',
                'office', 'end_user', 'purpose', 'delivery_location', 'delivery_date', 'delivery_term_days',
                'bac_resolution_number', 'bac_resolution_date', 'philgeps_reference', 'philgeps_posting_date',
                'approved_by', 'approval_date',
            ];

            $hasCorrection = false;
            foreach ($correctableFields as $field) {
                if (isset($data[$field])) {
                    $hasCorrection = true;
                    break;
                }
            }

            if (! $hasCorrection) {
                $validator->errors()->add('correction', 'At least one field must be corrected.');
            }

            // Validate BAC resolution date consistency
            if (isset($data['bac_resolution_number']) && ! isset($data['bac_resolution_date'])) {
                $validator->errors()->add('bac_resolution_date', 'BAC resolution date is required when providing BAC resolution number.');
            }

            if (isset($data['bac_resolution_date']) && ! isset($data['bac_resolution_number'])) {
                $validator->errors()->add('bac_resolution_number', 'BAC resolution number is required when providing BAC resolution date.');
            }

            // Validate PhilGEPS posting date consistency
            if (isset($data['philgeps_reference']) && ! isset($data['philgeps_posting_date'])) {
                $validator->errors()->add('philgeps_posting_date', 'PhilGEPS posting date is required when providing PhilGEPS reference.');
            }

            if (isset($data['philgeps_posting_date']) && ! isset($data['philgeps_reference'])) {
                $validator->errors()->add('philgeps_reference', 'PhilGEPS reference is required when providing PhilGEPS posting date.');
            }

            // Validate approval date consistency
            if (isset($data['approved_by']) && ! isset($data['approval_date'])) {
                $validator->errors()->add('approval_date', 'Approval date is required when providing approved by.');
            }

            if (isset($data['approval_date']) && ! isset($data['approved_by'])) {
                $validator->errors()->add('approved_by', 'Approved by is required when providing approval date.');
            }
        });
    }
}
