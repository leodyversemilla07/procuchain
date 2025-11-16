<?php

declare(strict_types=1);

namespace App\Http\Requests\Procurement;

use App\Enums\DocumentTypeEnums;
use App\Enums\ProcurementCategoryEnums;
use App\Enums\ProcurementModeEnums;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage procurement initiation');
    }

    public function rules(): array
    {
        return [
            // Basic Information - REQUIRED per RA 9184
            'pr_number' => ['required', 'string', 'regex:/^PR-\d{4}-\d{4}-\d{4}$/', 'max:100'],
            'ppmp_reference' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],

            // Financial Information (ABC = Approved Budget for Contract)
            'abc_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'funding_source' => ['required', 'string', 'max:255'],

            // Classification
            'category' => ['required', Rule::enum(ProcurementCategoryEnums::class)],
            'procurement_mode' => ['required', Rule::enum(ProcurementModeEnums::class)],

            // Municipal Office Information
            'office' => ['required', 'string', 'max:255'],
            'end_user' => ['nullable', 'string', 'max:255'],

            // Purpose
            'purpose' => ['required', 'string', 'max:2000'],

            // Delivery Details
            'delivery_location' => ['required', 'string', 'max:500'],
            'delivery_date' => ['required', 'date', 'after:today'],
            'delivery_term_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            // Prepared By
            'prepared_by' => ['required', 'string', 'max:255'],

            // Documents - Must use specific document types per RA 9184
            'files.*' => ['required', 'file', 'mimes:pdf', 'max:51200'], // 50MB max
            'document_types.*' => ['required', Rule::enum(DocumentTypeEnums::class)],
            'document_descriptions.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Configure validator to add custom validation rules
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateMandatoryDocuments($validator);
            $this->validateAbcAgainstMode($validator);
        });
    }

    /**
     * Validate that all mandatory documents are provided per RA 9184
     */
    protected function validateMandatoryDocuments($validator): void
    {
        $documentTypes = $this->input('document_types', []);
        $category = ProcurementCategoryEnums::tryFrom($this->input('category'));

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
                'Missing required documents per RA 9184: '.implode(', ', $missing).'. Please upload all mandatory documents before proceeding.'
            );
        }
    }

    /**
     * Validate ABC amount against procurement mode threshold per RA 9184 (Issue #9 enhanced)
     */
    protected function validateAbcAgainstMode($validator): void
    {
        $mode = ProcurementModeEnums::tryFrom($this->input('procurement_mode'));
        $abc = (float) $this->input('abc_amount', 0);

        if (! $mode || $abc <= 0) {
            return;
        }

        // Use the new isValidAmount method from enum
        if (! $mode->isValidAmount($abc)) {
            $suggestedMode = ProcurementModeEnums::suggestModeForAmount($abc);
            $threshold = $mode->thresholdAmount();

            $validator->errors()->add(
                'procurement_mode',
                sprintf(
                    'The selected procurement mode "%s" has a threshold of %s. Your ABC amount of ₱%s exceeds this threshold. Suggested mode: "%s". Please refer to RA 9184 Section 18 for proper procurement mode selection.',
                    $mode->getDisplayName(),
                    $mode->getAmountRange(),
                    number_format($abc, 2),
                    $suggestedMode->getDisplayName()
                )
            );
        }
    }

    public function messages(): array
    {
        return [
            'pr_number.required' => 'Purchase Request (PR) number is required per RA 9184 IRR-A Section 7.',
            'pr_number.regex' => 'PR number must follow format: PR-YYYY-####-#### (e.g., PR-2025-0001-0001).',
            'ppmp_reference.required' => 'PPMP reference is required to verify procurement is in approved annual plan per RA 9184.',
            'abc_amount.required' => 'Approved Budget for Contract (ABC) is required per RA 9184.',
            'category.required' => 'Procurement category must be specified (Goods/Infrastructure/Consulting).',
            'procurement_mode.required' => 'Procurement mode must comply with RA 9184 requirements.',
            'office.required' => 'Municipal/City Office is required.',
            'purpose.required' => 'Purpose of procurement must be clearly stated for transparency.',
            'delivery_location.required' => 'Delivery location must be specified.',
            'delivery_date.required' => 'Expected delivery date is required.',
            'prepared_by.required' => 'Prepared by field is required to identify the procurement officer.',
            'files.*.required' => 'All required documents must be uploaded per RA 9184 IRR-A.',
            'files.*.mimes' => 'All documents must be in PDF format for blockchain storage.',
            'document_types.*.required' => 'Document type must be specified for each uploaded file.',
            'document_types.*.enum' => 'Invalid document type. Please select from the provided list of RA 9184 compliant document types.',
        ];
    }
}
