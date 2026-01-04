<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRoleEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BidEvaluationDocumentsRequest extends FormRequest
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
            'pr_number' => 'required|string|max:50',
            'procurement_title' => 'required|string|min:5|max:255',
            'summary_file' => 'required|file|mimes:pdf|max:51200',
            'abstract_file' => 'required|file|mimes:pdf|max:51200',
            'evaluation_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'evaluators' => 'required|array|min:1',
            'evaluators.*.name' => 'required|string|min:1|max:255',
            'evaluators.*.position' => 'required|string|min:1|max:255',
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
            'summary_file.max' => 'The summary file must not exceed 50MB in size.',
            'summary_file.mimes' => 'Only PDF files are allowed.',
            'abstract_file.max' => 'The abstract file must not exceed 50MB in size.',
            'abstract_file.mimes' => 'Only PDF files are allowed.',
            'evaluators.required' => 'At least one evaluator is required.',
            'evaluators.array' => 'Evaluators must be provided as an array.',
            'evaluators.min' => 'At least one evaluator is required.',
            'evaluators.*.name.required' => 'Each evaluator must have a name.',
            'evaluators.*.position.required' => 'Each evaluator must have a position.',
        ];
    }
}
