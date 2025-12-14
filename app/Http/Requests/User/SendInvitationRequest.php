<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create users');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Email must not already be a user
                Rule::unique('users', 'email'),
                // Email must not have a pending invitation
                function ($attribute, $value, $fail) {
                    $pendingInvitation = UserInvitation::where('email', $value)
                        ->pending()
                        ->exists();

                    if ($pendingInvitation) {
                        $fail('A pending invitation already exists for this email address.');
                    }
                },
            ],
            'role' => ['required', 'string', Rule::in(['bac_secretariat', 'bac_chairman', 'hope'])],
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter the invitee\'s name.',
            'email.required' => 'Please enter the invitee\'s email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'A user with this email address already exists.',
            'role.required' => 'Please select a role for the invitee.',
            'role.in' => 'The selected role is invalid.',
        ];
    }
}
