<?php

namespace App\Http\Requests;

class ReportExportRequest extends ReportFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'format' => ['required', 'string', 'in:json,csv,pdf'],
        ]);
    }
}
