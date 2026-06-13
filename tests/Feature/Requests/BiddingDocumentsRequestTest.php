<?php

use App\Enums\UserRole;
use App\Http\Requests\Procurement\BiddingDocumentsRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

describe('BiddingDocumentsRequest', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->user->assignRole(UserRole::BAC_SECRETARIAT->value);
        $this->actingAs($this->user);
    });

    describe('authorization', function () {
        test('it authorizes bac secretariat users', function () {
            $request = new BiddingDocumentsRequest;
            $request->setUserResolver(fn () => $this->user);

            expect($request->authorize())->toBeTrue();
        });

        test('it denies unauthorized users', function () {
            $unauthorizedUser = User::factory()->create();
            $unauthorizedUser->assignRole('admin');

            $this->actingAs($unauthorizedUser);

            $request = new BiddingDocumentsRequest;

            expect($request->authorize())->toBeFalse();
        });
    });

    describe('validation rules', function () {
        test('it passes with valid data', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024, 'application/pdf'),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('pr_number validation', function () {
        test('it requires pr_number', function () {
            $data = [
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('pr_number'))->toBeTrue();
        });

        test('it rejects pr_number exceeding 50 characters', function () {
            $data = [
                'pr_number' => str_repeat('A', 51),
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('pr_number'))->toBeTrue();
        });
    });

    describe('procurement_title validation', function () {
        test('it requires procurement_title', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_title'))->toBeTrue();
        });

        test('it rejects procurement_title less than 5 characters', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Test',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_title'))->toBeTrue();
        });

        test('it accepts procurement_title with minimum 5 characters', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Tests',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it rejects procurement_title exceeding 255 characters', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => str_repeat('A', 256),
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_title'))->toBeTrue();
        });
    });

    describe('bidding_document_File validation', function () {
        test('it requires bidding_document_File', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('bidding_document_File'))->toBeTrue();
        });

        test('it accepts PDF BlockchainFiles', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('document.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it rejects non-PDF BlockchainFiles', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('document.docx', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('bidding_document_File'))->toBeTrue();
        });

        test('it rejects BlockchainFiles exceeding 50MB', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 51201, 'application/pdf'), // 50MB + 1KB
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('bidding_document_File'))->toBeTrue();
        });

        test('it accepts BlockchainFiles up to 50MB', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 51200), // 50MB
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('issuance_date validation', function () {
        test('it requires issuance_date', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('issuance_date'))->toBeTrue();
        });

        test('it accepts valid Y-m-d date format', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it rejects invalid date formats', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '15-01-2024',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('issuance_date'))->toBeTrue();
        });

        test('it rejects future issuance dates', function () {
            $futureDate = now()->addDays(1)->format('Y-m-d');

            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => $futureDate,
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('issuance_date'))->toBeTrue();
        });

        test('it accepts today as issuance date', function () {
            $today = now()->format('Y-m-d');

            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => $today,
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('validity period validation', function () {
        test('it requires both validity_period_start and validity_period_end', function () {
            $dataStart = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($dataStart, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('validity_period_start'))->toBeTrue();

            $dataEnd = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
            ];

            $validator2 = Validator::make($dataEnd, $request->rules());

            expect($validator2->fails())->toBeTrue();
            expect($validator2->errors()->has('validity_period_end'))->toBeTrue();
        });

        test('it validates that start date is before or equal to end date', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-02-20',
                'validity_period_end' => '2024-01-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('validity_period_start'))->toBeTrue();
        });

        test('it validates that end date is after start date', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-02-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('validity_period_end'))->toBeTrue();
        });

        test('it accepts valid validity period', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('custom error messages', function () {
        test('it provides custom message for File size', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 51201, 'application/pdf'), // 50MB + 1KB
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules(), $request->messages());

            expect($validator->errors()->first('bidding_document_File'))->toContain('50MB');
        });

        test('it provides custom message for File type', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.docx', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-01-20',
                'validity_period_end' => '2024-02-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules(), $request->messages());

            expect($validator->errors()->first('bidding_document_File'))->toContain('PDF');
        });

        test('it provides custom message for validity period', function () {
            $data = [
                'pr_number' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'bidding_document_File' => UploadedFile::fake()->create('bidding.pdf', 1024),
                'issuance_date' => '2024-01-15',
                'validity_period_start' => '2024-02-20',
                'validity_period_end' => '2024-01-20',
            ];

            $request = new BiddingDocumentsRequest;
            $validator = Validator::make($data, $request->rules(), $request->messages());

            expect($validator->errors()->first('validity_period_start'))->toContain('before or equal');
        });
    });
});
