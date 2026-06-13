<?php

declare(strict_types=1);

namespace App\Http\Requests\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('manage procurement initiation');
    }

    public function rules(): array
    {
        return [
            // Basic Information - REQUIRED per RA 12009 (NGPA)
            'pr_number' => ['required', 'string', 'regex:/^PR-\d{4}-\d{3}-\d{4}$/', 'max:100'],
            'app_reference' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],

            // Financial Information (ABC = Approved Budget for Contract)
            'abc_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'funding_source' => ['required', 'string', 'max:255'],

            // Classification
            'category' => ['required', Rule::enum(ProcurementCategory::class)],
            'procurement_mode' => ['required', Rule::enum(ProcurementMode::class)],
            'negotiated_procurement_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(ProcurementMode::negotiatedProcurementSubTypes()))],

            // Municipal Office Information
            'office' => ['required', 'string', 'max:255'],
            'end_user' => ['nullable', 'string', 'max:255'],
            'other_end_user' => ['nullable', 'required_if:end_user,Other', 'string', 'max:255'],

            // Other fields for custom entries
            'other_description' => ['nullable', 'required_if:description,Other', 'string', 'max:5000'],
            'other_funding_source' => ['nullable', 'required_if:funding_source,Other Sources', 'string', 'max:255'],

            // Prepared By
            'prepared_by' => ['required', 'string', 'max:255'],

            // Documents - Optional to support progressive upload (can upload after initiation)
            'Files' => ['nullable', 'array'],
            'BlockchainFiles.*' => ['mimes:pdf', 'max:51200'], // 50MB max
            'document_types' => ['nullable', 'array'],
            'document_types.*' => ['required_with:BlockchainFiles.*', Rule::enum(DocumentTypeEnums::class)],
            'document_descriptions' => ['nullable', 'array'],
            'document_descriptions.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Configure validator to add custom validation rules
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Only validate documents if BlockchainFiles are being uploaded with this request
            // Documents can be uploaded progressively after procurement creation
            if ($this->hasFile('Files') || ! empty($this->input('Files'))) {
                $this->validateMandatoryDocuments($validator);
            }
            $this->validateAbcAgainstMode($validator);
            $this->validateNegotiatedProcurementType($validator);
        });
    }

    /**
     * Validate that all mandatory documents are provided per RA 12009 (NGPA)
     * Note: Only validates if BlockchainFiles are provided (supports progressive upload)
     */
    protected function validateMandatoryDocuments($validator): void
    {
        $documentTypes = $this->input('document_types', []);
        $BlockchainFiles = $this->input('Files', []);

        $category = ProcurementCategory::tryFrom($this->input('category'));

        if (! $category) {
            return; // Category validation will catch this
        }

        // Get mandatory documents for this category
        $requiredDocs = DocumentTypeEnums::getMandatoryForCategory($category);
        $providedTypes = array_map(
            fn ($type) => DocumentTypeEnums::tryFrom($type),
            $documentTypes
        );
        $providedTypes = array_filter($providedTypes); // Remove nulls

        // Check each required document
        $missing = [];
        foreach ($requiredDocs as $requiredDoc) {
            if (! in_array($requiredDoc, $providedTypes, true)) {
                $missing[] = $requiredDoc->getDisplayName();
            }
        }

        if (! empty($missing)) {
            $validator->errors()->add(
                'document_types',
                'Missing required documents per RA 12009 (NGPA): '.implode(', ', $missing).'. Please upload all mandatory documents before proceeding.'
            );
        }
    }

    /**
     * Validate ABC amount against procurement mode threshold per RA 12009 (NGPA)
     */
    protected function validateAbcAgainstMode($validator): void
    {
        $mode = ProcurementMode::tryFrom($this->input('procurement_mode'));
        $abc = (float) $this->input('abc_amount', 0);

        if (! $mode || $abc <= 0) {
            return;
        }

        // Use the new isValidAmount method from enum
        if (! $mode->isValidAmount($abc)) {
            $suggestedMode = ProcurementMode::suggestModeForAmount($abc);
            $threshold = $mode->thresholdAmount();

            $validator->errors()->add(
                'procurement_mode',
                sprintf(
                    'The selected procurement mode "%s" has a threshold of %s. Your ABC amount of ₱%s exceeds this threshold. Suggested mode: "%s". Please refer to RA 12009 Section 26 for proper procurement mode selection.',
                    $mode->getDisplayName(),
                    $mode->getAmountRange(),
                    number_format($abc, 2),
                    $suggestedMode->getDisplayName()
                )
            );
        }
    }

    /**
     * Validate that negotiated_procurement_type is required when procurement_mode is negotiated_procurement
     * Per NGPA IRR Section 35
     */
    protected function validateNegotiatedProcurementType($validator): void
    {
        $mode = $this->input('procurement_mode');
        $negotiatedType = $this->input('negotiated_procurement_type');

        if ($mode === ProcurementMode::NEGOTIATED_PROCUREMENT->value && empty($negotiatedType)) {
            $validator->errors()->add(
                'negotiated_procurement_type',
                'The negotiated procurement type is required when procurement mode is Negotiated Procurement per RA 12009 Section 35.'
            );
        }

        if ($mode !== ProcurementMode::NEGOTIATED_PROCUREMENT->value && ! empty($negotiatedType)) {
            $validator->errors()->add(
                'negotiated_procurement_type',
                'The negotiated procurement type should only be specified when procurement mode is Negotiated Procurement.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'pr_number.required' => 'Purchase Request (PR) number is required per RA 12009 (NGPA) IRR Section 7.',
            'pr_number.regex' => 'PR number must follow format: PR-YYYY-000-0000 (e.g., PR-2025-001-0001).',
            'app_reference.required' => 'AIP code reference is required to verify procurement is in approved annual investment plan per RA 12009.',
            'abc_amount.required' => 'Approved Budget for Contract (ABC) is required per RA 12009.',
            'category.required' => 'Procurement category must be specified (Goods/Infrastructure/Consulting).',
            'procurement_mode.required' => 'Procurement mode must comply with RA 12009 requirements.',
            'negotiated_procurement_type.in' => 'The negotiated procurement type must be one of the valid sub-types per RA 12009 Section 35.',
            'office.required' => 'Municipal/City Office is required.',
            'prepared_by.required' => 'Prepared by field is required to identify the procurement officer.',
            'other_description.required_if' => 'Please specify the description when selecting "Other".',
            'other_funding_source.required_if' => 'Please specify the funding source when selecting "Other Sources".',
            'other_end_user.required_if' => 'Please specify the end user when selecting "Other".',
            'BlockchainFiles.*.required' => 'All required documents must be uploaded per RA 12009 (NGPA) IRR.',
            'BlockchainFiles.*.mimes' => 'All documents must be in PDF format for blockchain storage.',
            'document_types.*.required' => 'Document type must be specified for each uploaded File.',
            'document_types.*.enum' => 'Invalid document type. Please select from the provided list of RA 12009 compliant document types.',
        ];
    }
}
