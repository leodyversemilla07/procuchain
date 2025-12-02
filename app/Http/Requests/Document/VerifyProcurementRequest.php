<?php

declare(strict_types=1);

namespace App\Http\Requests\Document;

use App\Enums\StageEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request for verifying procurement documents
 */
class VerifyProcurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'stage' => [
                'nullable',
                'string',
                Rule::in(array_map(fn ($stage) => $stage->value, StageEnums::cases())),
            ],
            'verification_types' => [
                'nullable',
                'array',
            ],
            'verification_types.*' => [
                'string',
                Rule::in(['integrity', 'completeness', 'cross_reference', 'compliance', 'all']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'stage.in' => 'Invalid procurement stage specified.',
            'verification_types.*.in' => 'Invalid verification type. Allowed: integrity, completeness, cross_reference, compliance, all.',
        ];
    }

    /**
     * Get the stage enum from request
     */
    public function getStageEnum(): ?StageEnums
    {
        $stage = $this->input('stage');

        return $stage ? StageEnums::tryFrom($stage) : null;
    }

    /**
     * Get verification types to run
     */
    public function getVerificationTypes(): array
    {
        $types = $this->input('verification_types', ['all']);

        if (in_array('all', $types, true)) {
            return ['integrity', 'completeness', 'cross_reference', 'compliance'];
        }

        return $types;
    }
}
