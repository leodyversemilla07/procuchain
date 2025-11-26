<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['admin', 'bac_chairman', 'bac_secretariat']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'correction_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'correction_type' => ['required', Rule::in(['replace', 'invalidate'])],
            'corrected_file' => ['required_if:correction_type,replace', 'file', 'mimes:pdf', 'max:8192'],
            'pr_number' => ['required', 'string'],
            'procurement_title' => ['required', 'string'],
            'original_document_hash' => ['required', 'string'],
            'original_txid' => ['nullable', 'string'],
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
            'correction_reason.required' => 'Please provide a reason for the document correction.',
            'correction_reason.min' => 'The correction reason must be at least 10 characters.',
            'correction_reason.max' => 'The correction reason must not exceed 1000 characters.',
            'correction_type.required' => 'Please select a correction type.',
            'correction_type.in' => 'Invalid correction type selected.',
            'corrected_file.required_if' => 'A corrected file is required when replacing a document.',
            'corrected_file.file' => 'The corrected file must be a valid file.',
            'corrected_file.mimes' => 'Only PDF files are allowed.',
            'corrected_file.max' => 'The corrected file must not exceed 8MB.',
            'pr_number.required' => 'Procurement ID is required.',
            'pr_number.exists' => 'The specified procurement does not exist.',
            'procurement_title.required' => 'Procurement title is required.',
            'original_document_hash.required' => 'Original document hash is required.',
        ];
    }
}
