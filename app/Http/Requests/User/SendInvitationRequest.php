<?php

namespace App\Http\Requests\User;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\UserInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::CREATE_USERS->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
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
                Rule::unique('users', 'email'),
                function ($attribute, $value, $fail) {
                    $pendingInvitation = UserInvitation::where('email', $value)
                        ->pending()
                        ->exists();

                    if ($pendingInvitation) {
                        $fail('A pending invitation already exists for this email address.');
                    }
                },
            ],
            'role' => ['required', 'string', Rule::in([
                UserRole::BAC_SECRETARIAT->value,
                UserRole::BAC_CHAIRMAN->value,
                UserRole::HOPE->value,
            ])],
        ];
    }

    /**
     * @return array<string, string>
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
