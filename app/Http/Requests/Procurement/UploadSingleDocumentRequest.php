<?php

namespace App\Http\Requests\Procurement;

use App\Enums\DocumentTypeEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSingleDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('bac_secretariat') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'document_file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:51200', // 50MB for combined procurement initiation document
            ],
            'document_type' => [
                'required',
                'string',
                Rule::in(array_map(fn ($enum) => $enum->value, DocumentTypeEnums::cases())),
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_file.required' => 'Please select a file to upload.',
            'document_file.file' => 'The uploaded item must be a valid file.',
            'document_file.mimes' => 'Only PDF files are allowed.',
            'document_file.max' => 'File size must not exceed 50MB.',
            'document_type.required' => 'Document type is required.',
            'document_type.in' => 'Invalid document type selected.',
            'description.max' => 'Description must not exceed 500 characters.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'document_file' => 'document file',
            'document_type' => 'document type',
        ];
    }
}
