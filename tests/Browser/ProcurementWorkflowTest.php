<?php

declare(strict_types=1);

use App\DataTransferObjects\ProcurementData;
use App\Enums\ProcurementCategory;
use App\Enums\ProcurementMode;
use App\Enums\StageEnums;
use App\Models\User;
use App\Repositories\ProcurementRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SeedsPermissions;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);
uses(SeedsPermissions::class);

describe('Procurement Initiation Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create([
            'blockchain_address' => 'test-blockchain-address',
        ]);
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('displays procurement initiation form', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.initiation.index'));

        $page->assertSee('Procurement Initiation')
            ->assertSee('Basic Information')
            ->assertSee('Create Procurement')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('shows draft and next-step controls', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.initiation.index'));

        $page->assertSee('Save Draft')
            ->assertSee('Next: Progressive Document Upload')
            ->assertNoJavascriptErrors();
    });

    it('displays all required form sections', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.initiation.index'));

        $page->assertSee('Basic Information')
            ->assertSee('Classification & Budget')
            ->assertSee('Office & Purpose')
            ->assertNoJavascriptErrors();
    });
});

describe('Procurement List Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create();
        $this->bacSecretariat->assignRole('bac_secretariat');
    });

    it('displays procurement list with search functionality', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurements.index'));

        $page->assertSee('Procurement List')
            ->assertSee('New Procurement')
            ->assertNoJavascriptErrors();
    });

    it('allows filtering by stage', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurements.index'));

        $page->assertSee('Active')
            ->assertSee('Archived')
            ->assertSee('Refresh')
            ->assertNoJavascriptErrors();
    });

    it('shows the empty state when no procurements exist', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurements.index'));

        $page->assertSee('No procurements available yet')
            ->assertNoJavascriptErrors();
    });
});

describe('Request for Quotation (RFQ) Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create([
            'blockchain_address' => 'test-blockchain-address',
        ]);
        $this->bacSecretariat->assignRole('bac_secretariat');
        mockSmallValueProcurement($this->bacSecretariat);
    });

    it('displays RFQ stage page correctly', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $page->assertSee('Request for Quotation')
            ->assertSee('Workflow Progress')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('shows procurement context for RFQ stage', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $page->assertSee('Upload and verify documents for this stage')
            ->assertSee('PR-2024-001-0001')
            ->assertNoJavascriptErrors();
    });

    it('renders the RFQ workflow panel', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.pre-procurement.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::REQUEST_FOR_QUOTATION->value,
        ]));

        $page->assertSee('Workflow Progress')
            ->assertNoJavascriptErrors();
    });
});

describe('Abstract of Quotations Browser Flow', function () {
    beforeEach(function () {
        $this->seedPermissions();

        $this->bacSecretariat = User::factory()->create([
            'blockchain_address' => 'test-blockchain-address',
        ]);
        $this->bacSecretariat->assignRole('bac_secretariat');
        mockSmallValueProcurement($this->bacSecretariat);
    });

    it('displays Abstract of Quotations stage page correctly', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $page->assertSee('Abstract of Quotations')
            ->assertSee('Workflow Progress')
            ->assertNoJavascriptErrors()
            ->assertNoConsoleLogs();
    });

    it('shows procurement context for Abstract stage', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $page->assertSee('Upload and verify documents for this stage')
            ->assertSee('PR-2024-001-0001')
            ->assertNoJavascriptErrors();
    });

    it('renders the Abstract workflow panel', function () {
        $this->actingAs($this->bacSecretariat);

        $page = visit(route('bac-secretariat.procurement.bidding.show', [
            'pr_number' => 'PR-2024-001-0001',
            'stage' => StageEnums::ABSTRACT_OF_QUOTATIONS->value,
        ]));

        $page->assertSee('Workflow Progress')
            ->assertNoJavascriptErrors();
    });
});

function mockSmallValueProcurement(User $user, string $prNumber = 'PR-2024-001-0001'): void
{
    $repository = mock(ProcurementRepository::class);
    $repository->shouldReceive('findByProcurement')
        ->zeroOrMoreTimes()
        ->andReturn(new ProcurementData(
            prNumber: $prNumber,
            appReference: 'APP-2024-001',
            title: 'Test SVP Procurement',
            description: 'Test Description',
            abcAmount: 100000.00,
            fundingSource: 'General Fund',
            category: ProcurementCategory::GOODS,
            procurementMode: ProcurementMode::SMALL_VALUE_PROCUREMENT,
            office: 'Test Office',
            endUser: 'Test User',
            deliveryLocation: null,
            deliveryDate: null,
            deliveryTermDays: null,
            preparedBy: 'Test Preparer',
            bacResolutionNumber: null,
            bacResolutionDate: null,
            philgepsReference: null,
            philgepsPostingDate: null,
            approvedBy: null,
            approvalDate: null,
            status: 'in_progress',
            userId: (string) $user->id,
            createdAt: now()
        ));

    app()->instance(ProcurementRepository::class, $repository);
}
