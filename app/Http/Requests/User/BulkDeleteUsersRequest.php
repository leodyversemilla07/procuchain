<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('delete users');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Please select at least one user to delete.',
            'user_ids.array' => 'Invalid user selection format.',
            'user_ids.min' => 'Please select at least one user to delete.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Prevent deletion of authenticated user
        if ($this->has('user_ids')) {
            $userIds = array_filter($this->input('user_ids'), function ($id) {
                return $id !== $this->user()->id;
            });

            $this->merge([
                'user_ids' => array_values($userIds),
            ]);
        }
    }
}
