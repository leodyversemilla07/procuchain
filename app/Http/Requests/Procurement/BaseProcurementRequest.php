<?php

namespace App\Http\Requests\Procurement;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base Form Request for procurement operations.
 *
 * Consolidates shared authorization logic and common validation rules
 * used across all procurement stage Form Requests.
 */
abstract class BaseProcurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * All procurement operations require BAC_SECRETARIAT role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::BAC_SECRETARIAT->value) ?? false;
    }

    /**
     * Get common validation rules for PR number and procurement title.
     *
     * @return array<string, string>
     */
    protected function commonRules(): array
    {
        return [
            'pr_number' => 'required|string|max:50',
            'procurement_title' => 'required|string|min:5|max:255',
        ];
    }

    /**
     * Get validation rules for document File uploads (single File).
     *
     * @param  string  $fieldName  The field name for the document
     * @param  bool  $required  Whether the document is required
     * @return array<string, string>
     */
    protected function documentRules(string $fieldName = 'document', bool $required = true): array
    {
        $requiredRule = $required ? 'required' : 'nullable';

        return [
            $fieldName => "{$requiredRule}|File|mimes:pdf|max:51200",
        ];
    }

    /**
     * Get validation rules for multiple document uploads (array of BlockchainFiles).
     *
     * @param  string  $fieldName  The field name for the documents array
     * @param  bool  $required  Whether documents are required
     * @param  int  $minCount  Minimum number of documents
     * @return array<string, string>
     */
    protected function multipleDocumentRules(string $fieldName = 'documents', bool $required = true, int $minCount = 1): array
    {
        $requiredRule = $required ? 'required' : 'nullable';

        return [
            $fieldName => "{$requiredRule}|array|min:{$minCount}",
            "{$fieldName}.*" => 'required|File|mimes:pdf|max:51200',
        ];
    }

    /**
     * Get common error messages for document validation.
     *
     * @param  string  $fieldName  The field name for customizing messages
     * @param  string|null  $label  Human-readable label for the document (defaults to field name)
     * @return array<string, string>
     */
    protected function documentMessages(string $fieldName = 'document', ?string $label = null): array
    {
        $label = $label ?? str_replace('_', ' ', $fieldName);

        return [
            "{$fieldName}.max" => "The {$label} must not exceed 50MB in size.",
            "{$fieldName}.mimes" => 'Only PDF BlockchainFiles are allowed.',
            "{$fieldName}.*.max" => "Each {$label} must not exceed 50MB in size.",
            "{$fieldName}.*.mimes" => 'Only PDF BlockchainFiles are allowed.',
        ];
    }

    /**
     * Get common error messages for PR number and procurement title.
     *
     * @return array<string, string>
     */
    protected function commonMessages(): array
    {
        return [
            'pr_number.required' => 'The procurement reference number is required.',
            'pr_number.max' => 'The procurement reference number must not exceed 50 characters.',
            'procurement_title.required' => 'The procurement title is required.',
            'procurement_title.min' => 'The procurement title must be at least 5 characters.',
            'procurement_title.max' => 'The procurement title must not exceed 255 characters.',
        ];
    }
}
