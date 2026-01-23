<?php

use App\Http\Requests\Procurement\PreBidConferenceDocumentsRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDocumentsRequest;

describe('HasConferenceValidation Trait', function () {
    describe('conferenceRules are shared between both request classes', function () {
        it('PreProcurementConferenceDocumentsRequest includes conference validation rules', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            // Check for conference-specific rules
            expect($rules)->toHaveKey('minutes_file');
            expect($rules)->toHaveKey('attendance_file');
            expect($rules)->toHaveKey('meeting_date');
            expect($rules)->toHaveKey('participants');
            expect($rules)->toHaveKey('participants.*.name');
            expect($rules)->toHaveKey('participants.*.organization');
        });

        it('PreBidConferenceDocumentsRequest includes conference validation rules', function () {
            $request = new PreBidConferenceDocumentsRequest;
            $rules = $request->rules();

            // Check for conference-specific rules
            expect($rules)->toHaveKey('minutes_file');
            expect($rules)->toHaveKey('attendance_file');
            expect($rules)->toHaveKey('meeting_date');
            expect($rules)->toHaveKey('participants');
            expect($rules)->toHaveKey('participants.*.name');
            expect($rules)->toHaveKey('participants.*.organization');
        });

        it('both request classes have identical conference rules', function () {
            $preProcurementRequest = new PreProcurementConferenceDocumentsRequest;
            $preBidRequest = new PreBidConferenceDocumentsRequest;

            $preProcurementRules = $preProcurementRequest->rules();
            $preBidRules = $preBidRequest->rules();

            // Conference-specific rules should be identical
            $conferenceFields = ['minutes_file', 'attendance_file', 'meeting_date', 'participants', 'participants.*.name', 'participants.*.organization'];

            foreach ($conferenceFields as $field) {
                expect($preProcurementRules[$field])->toBe($preBidRules[$field], "Rule for {$field} should be identical in both requests");
            }
        });
    });

    describe('conferenceMessages are shared between both request classes', function () {
        it('PreProcurementConferenceDocumentsRequest includes conference validation messages', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $messages = $request->messages();

            // Check for conference-specific messages
            expect($messages)->toHaveKey('meeting_date.required');
            expect($messages)->toHaveKey('meeting_date.before_or_equal');
            expect($messages)->toHaveKey('participants.required');
            expect($messages)->toHaveKey('participants.*.name.required');
            expect($messages)->toHaveKey('participants.*.organization.required');
        });

        it('PreBidConferenceDocumentsRequest includes conference validation messages', function () {
            $request = new PreBidConferenceDocumentsRequest;
            $messages = $request->messages();

            // Check for conference-specific messages
            expect($messages)->toHaveKey('meeting_date.required');
            expect($messages)->toHaveKey('meeting_date.before_or_equal');
            expect($messages)->toHaveKey('participants.required');
            expect($messages)->toHaveKey('participants.*.name.required');
            expect($messages)->toHaveKey('participants.*.organization.required');
        });
    });

    describe('meeting_date validation', function () {
        it('meeting_date must be in YYYY-MM-DD format', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['meeting_date'])->toContain('date_format:Y-m-d');
        });

        it('meeting_date cannot be in the future', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['meeting_date'])->toContain('before_or_equal:today');
        });
    });

    describe('participants validation', function () {
        it('requires at least one participant', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['participants'])->toContain('min:1');
        });

        it('each participant must have a name with max 255 characters', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['participants.*.name'])->toContain('max:255');
        });

        it('each participant must have an organization with max 255 characters', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['participants.*.organization'])->toContain('max:255');
        });
    });

    describe('file validation', function () {
        it('minutes_file must be a PDF', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['minutes_file'])->toContain('mimes:pdf');
        });

        it('attendance_file must be a PDF', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['attendance_file'])->toContain('mimes:pdf');
        });

        it('files have max size of 50MB (51200KB)', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules['minutes_file'])->toContain('max:51200');
            expect($rules['attendance_file'])->toContain('max:51200');
        });
    });

    describe('common rules are still included', function () {
        it('includes pr_number validation from base class', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules)->toHaveKey('pr_number');
        });

        it('includes procurement_title validation from base class', function () {
            $request = new PreProcurementConferenceDocumentsRequest;
            $rules = $request->rules();

            expect($rules)->toHaveKey('procurement_title');
        });
    });
});
