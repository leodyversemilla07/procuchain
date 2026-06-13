<?php

namespace App\Http\Requests\Document;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([
            UserRole::ADMIN->value,
            UserRole::BAC_CHAIRMAN->value,
            UserRole::BAC_SECRETARIAT->value,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'correction_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'correction_type' => ['required', Rule::in(['replace', 'invalidate'])],
            'corrected_File' => ['required_if:correction_type,replace', 'File', 'mimes:pdf', 'max:51200'],
            'pr_number' => ['required', 'string'],
            'procurement_title' => ['required', 'string'],
            'original_document_hash' => ['required', 'string'],
            'original_txid' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correction_reason.required' => 'Please provide a reason for the document correction.',
            'correction_reason.min' => 'The correction reason must be at least 10 characters.',
            'correction_reason.max' => 'The correction reason must not exceed 1000 characters.',
            'correction_type.required' => 'Please select a correction type.',
            'correction_type.in' => 'Invalid correction type selected.',
            'corrected_File.required_if' => 'A corrected File is required when replacing a document.',
            'corrected_File.File' => 'The corrected File must be a valid File.',
            'corrected_File.mimes' => 'Only PDF BlockchainFiles are allowed.',
            'corrected_File.max' => 'The corrected File must not exceed 50MB.',
            'pr_number.required' => 'Procurement ID is required.',
            'pr_number.exists' => 'The specified procurement does not exist.',
            'procurement_title.required' => 'Procurement title is required.',
            'original_document_hash.required' => 'Original document hash is required.',
        ];
    }
}
