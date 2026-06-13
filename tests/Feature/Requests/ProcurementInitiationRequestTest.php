<?php

use App\Enums\UserRole;
use App\Http\Requests\Procurement\InitiateProcurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

describe('InitiateProcurementRequest', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->user->assignRole(UserRole::BAC_SECRETARIAT->value);
        $this->actingAs($this->user);
    });

    describe('authorization', function () {
        test('it authorizes bac secretariat users', function () {
            $request = new InitiateProcurementRequest;
            $request->setUserResolver(fn () => $this->user);

            expect($request->authorize())->toBeTrue();
        });

        test('it denies unauthorized users', function () {
            $unauthorizedUser = User::factory()->create();
            // No role assigned

            $this->actingAs($unauthorizedUser);

            $request = new InitiateProcurementRequest;
            $request->setUserResolver(fn () => $unauthorizedUser);

            expect($request->authorize())->toBeFalse();
        });

        test('it denies guest users', function () {
            auth()->logout();

            $request = new InitiateProcurementRequest;
            $request->setUserResolver(fn () => null);

            expect($request->authorize())->toBeFalse();
        });
    });

    describe('validation rules', function () {
        test('it passes with valid data', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
                'Files' => [
                    UploadedFile::fake()->create('document1.pdf', 1024, 'application/pdf'),
                ],
                'document_types' => [
                    'project_proposal',
                ],
            ];

            $request = new InitiateProcurementRequest;
            $request->setUserResolver(fn () => $this->user);
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it passes without optional fields', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('pr_number validation', function () {
        test('it requires pr_number', function () {
            $data = [
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('pr_number'))->toBeTrue();
        });

        test('it validates pr_number format', function () {
            $data = [
                'pr_number' => 'INVALID-123',
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('pr_number'))->toBeTrue();
        });

        test('it accepts valid pr_number format', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('title validation', function () {
        test('it requires title', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('title'))->toBeTrue();
        });

        test('it rejects title exceeding 255 characters', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'title' => str_repeat('A', 256),
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('title'))->toBeTrue();
        });
    });

    describe('BlockchainFiles validation', function () {
        test('it accepts valid PDF BlockchainFiles', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
                'Files' => [
                    UploadedFile::fake()->create('document1.pdf', 1024, 'application/pdf'),
                    UploadedFile::fake()->create('document2.pdf', 1024, 'application/pdf'),
                    UploadedFile::fake()->create('document3.pdf', 1024, 'application/pdf'),
                    UploadedFile::fake()->create('document4.pdf', 1024, 'application/pdf'),
                ],
                'document_types' => [
                    'purchase_request',
                    'certificate_of_funds',
                    'ppmp_entry',
                    'technical_specifications',
                ],
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it rejects non-PDF BlockchainFiles', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
                'Files' => [
                    UploadedFile::fake()->create('document1.docx', 1024),
                ],
                'document_types' => [
                    'project_proposal',
                ],
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('BlockchainFiles.0'))->toBeTrue();
        });

        test('it rejects BlockchainFiles exceeding 50MB', function () {
            $data = [
                'pr_number' => 'PR-2024-001-0001',
                'app_reference' => 'APP-2024-001',
                'title' => 'Construction of Municipal Building',
                'description' => 'Construction project for municipal building',
                'abc_amount' => 1000000.00,
                'funding_source' => 'Local Government Fund',
                'category' => 'infrastructure_projects',
                'procurement_mode' => 'competitive_bidding',
                'office' => 'Engineering Office',
                'prepared_by' => 'John Doe',
                'Files' => [
                    UploadedFile::fake()->create('document1.pdf', 51201, 'application/pdf'),
                ],
                'document_types' => [
                    'project_proposal',
                ],
            ];

            $request = new InitiateProcurementRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('BlockchainFiles.0'))->toBeTrue();
        });
    });
});
