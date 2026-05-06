<?php

namespace App\Http\Requests\Procurement\Traits;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation rules for conference document requests.
 *
 * Used by PreProcurementConferenceDocumentsRequest and PreBidConferenceDocumentsRequest
 * which have identical validation requirements for:
 * - Minutes file (PDF)
 * - Attendance file (PDF)
 * - Meeting date (past or today)
 * - Participants array with name and organization
 */
trait HasConferenceValidation
{
    /**
     * Get the validation rules for conference documents.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function conferenceRules(): array
    {
        return [
            ...$this->documentRules('minutes_file'),
            ...$this->documentRules('attendance_file'),
            'meeting_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'participants' => 'required|array|min:1',
            'participants.*.name' => 'required|string|min:1|max:255',
            'participants.*.organization' => 'required|string|min:1|max:255',
        ];
    }

    /**
     * Get the error messages for conference document validation.
     *
     * @param  string  $minutesLabel  Human-readable label for minutes file
     * @param  string  $attendanceLabel  Human-readable label for attendance file
     * @return array<string, string>
     */
    protected function conferenceMessages(
        string $minutesLabel = 'minutes file',
        string $attendanceLabel = 'attendance file'
    ): array {
        return [
            ...$this->documentMessages('minutes_file', $minutesLabel),
            ...$this->documentMessages('attendance_file', $attendanceLabel),
            'meeting_date.required' => 'The meeting date is required.',
            'meeting_date.date_format' => 'The meeting date must be in YYYY-MM-DD format.',
            'meeting_date.before_or_equal' => 'The meeting date cannot be in the future.',
            'participants.required' => 'At least one participant is required.',
            'participants.array' => 'Participants must be provided as an array.',
            'participants.min' => 'At least one participant is required.',
            'participants.*.name.required' => 'Each participant must have a name.',
            'participants.*.name.string' => 'Each participant name must be a string.',
            'participants.*.name.min' => 'Each participant name must not be empty.',
            'participants.*.name.max' => 'Each participant name must not exceed 255 characters.',
            'participants.*.organization.required' => 'Each participant must have an organization.',
            'participants.*.organization.string' => 'Each participant organization must be a string.',
            'participants.*.organization.min' => 'Each participant organization must not be empty.',
            'participants.*.organization.max' => 'Each participant organization must not exceed 255 characters.',
        ];
    }
}
