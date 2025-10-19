<?php

use App\Enums\UserRoleEnums;
use App\Http\Requests\Procurement\ProcurementInitiationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

describe('ProcurementInitiationRequest', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->user->assignRole(UserRoleEnums::BAC_SECRETARIAT->value);
        $this->actingAs($this->user);
    });

    describe('authorization', function () {
        test('it authorizes bac secretariat users', function () {
            $request = new ProcurementInitiationRequest;
            $request->setUserResolver(fn () => $this->user);

            expect($request->authorize())->toBeTrue();
        });

        test('it denies unauthorized users', function () {
            $unauthorizedUser = User::factory()->create();
            $unauthorizedUser->assignRole('admin');

            $this->actingAs($unauthorizedUser);

            $request = new ProcurementInitiationRequest;

            expect($request->authorize())->toBeFalse();
        });

        test('it denies guest users', function () {
            auth()->logout();

            $request = new ProcurementInitiationRequest;
            $request->setUserResolver(fn () => null);

            expect($request->authorize())->toBeFalse();
        });
    });

    describe('validation rules', function () {
        test('it passes with valid data', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [
                    UploadedFile::fake()->create('document1.pdf', 1024, 'application/pdf'),
                ],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'submission_date' => '2024-01-15',
                        'municipal_offices' => 'Engineering Office',
                        'signatories' => [
                            ['name' => 'John Doe', 'position' => 'BAC Chairman'],
                        ],
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it passes without optional fields', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [
                    UploadedFile::fake()->create('document1.pdf', 1024, 'application/pdf'),
                ],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('procurement_id validation', function () {
        test('it requires procurement_id', function () {
            $data = [
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024, 'application/pdf')],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_id'))->toBeTrue();
        });

        test('it requires procurement_id to be a string', function () {
            $data = [
                'procurement_id' => 12345,
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_id'))->toBeTrue();
        });

        test('it rejects procurement_id exceeding 50 characters', function () {
            $data = [
                'procurement_id' => str_repeat('A', 51),
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_id'))->toBeTrue();
        });
    });

    describe('procurement_title validation', function () {
        test('it requires procurement_title', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_title'))->toBeTrue();
        });

        test('it requires minimum 5 characters', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Test',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_title'))->toBeTrue();
        });

        test('it rejects procurement_title exceeding 255 characters', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => str_repeat('A', 256),
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('procurement_title'))->toBeTrue();
        });
    });

    describe('files validation', function () {
        test('it requires files array', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('files'))->toBeTrue();
        });

        test('it requires at least one file', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('files'))->toBeTrue();
        });

        test('it rejects non-PDF files', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [
                    UploadedFile::fake()->create('document1.docx', 1024),
                ],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('files.0'))->toBeTrue();
        });

        test('it rejects files exceeding 10MB', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [
                    UploadedFile::fake()->create('document1.pdf', 10241, 'application/pdf'),
                ],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('files.0'))->toBeTrue();
        });

        test('it accepts multiple PDF files', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [
                    UploadedFile::fake()->create('document1.pdf', 1024, 'application/pdf'),
                    UploadedFile::fake()->create('document2.pdf', 2048, 'application/pdf'),
                ],
                'metadata' => [
                    ['document_type' => 'Project Proposal'],
                    ['document_type' => 'Budget Breakdown'],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('metadata validation', function () {
        test('it requires metadata array', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata'))->toBeTrue();
        });

        test('it requires at least one metadata entry', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata'))->toBeTrue();
        });

        test('it requires document_type in metadata', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    ['submission_date' => '2024-01-15'],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata.0.document_type'))->toBeTrue();
        });

        test('it rejects document_type exceeding 255 characters', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    ['document_type' => str_repeat('A', 256)],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata.0.document_type'))->toBeTrue();
        });
    });

    describe('submission_date validation', function () {
        test('it accepts valid date in Y-m-d format', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'submission_date' => '2024-01-15',
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it rejects future dates', function () {
            $futureDate = now()->addDays(1)->format('Y-m-d');

            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'submission_date' => $futureDate,
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata.0.submission_date'))->toBeTrue();
        });

        test('it rejects invalid date format', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'submission_date' => '15-01-2024',
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata.0.submission_date'))->toBeTrue();
        });
    });

    describe('signatories validation', function () {
        test('it accepts valid signatories', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'signatories' => [
                            ['name' => 'John Doe', 'position' => 'BAC Chairman'],
                            ['name' => 'Jane Smith', 'position' => 'BAC Member'],
                        ],
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->passes())->toBeTrue();
        });

        test('it requires name for each signatory', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'signatories' => [
                            ['position' => 'BAC Chairman'],
                        ],
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata.0.signatories.0.name'))->toBeTrue();
        });

        test('it requires position for each signatory', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'signatories' => [
                            ['name' => 'John Doe'],
                        ],
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata.0.signatories.0.position'))->toBeTrue();
        });

        test('it rejects signatory name exceeding 255 characters', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [UploadedFile::fake()->create('document1.pdf', 1024)],
                'metadata' => [
                    [
                        'document_type' => 'Project Proposal',
                        'signatories' => [
                            ['name' => str_repeat('A', 256), 'position' => 'BAC Chairman'],
                        ],
                    ],
                ],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules());

            expect($validator->fails())->toBeTrue();
            expect($validator->errors()->has('metadata.0.signatories.0.name'))->toBeTrue();
        });
    });

    describe('custom error messages', function () {
        test('it provides custom message for file size', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [
                    UploadedFile::fake()->create('document1.pdf', 10241, 'application/pdf'),
                ],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules(), $request->messages());

            expect($validator->errors()->first('files.0'))->toContain('10MB');
        });

        test('it provides custom message for file type', function () {
            $data = [
                'procurement_id' => 'PROC-2024-001',
                'procurement_title' => 'Construction of Municipal Building',
                'files' => [
                    UploadedFile::fake()->create('document1.docx', 1024),
                ],
                'metadata' => [['document_type' => 'Project Proposal']],
            ];

            $request = new ProcurementInitiationRequest;
            $validator = Validator::make($data, $request->rules(), $request->messages());

            expect($validator->errors()->first('files.0'))->toContain('PDF');
        });
    });
});
