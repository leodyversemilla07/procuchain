<?php

use App\Handlers\BacResolution\BacResolutionDocumentHandler;
use App\Handlers\BiddingDocuments\BiddingDocumentsHandler;
use App\Handlers\BidEvaluation\BidEvaluationHandler;
use App\Handlers\BidOpening\BidOpeningHandler;
use App\Handlers\Completion\CompletionDocumentsHandler;
use App\Handlers\Completion\CompletionProcessHandler;
use App\Handlers\Monitoring\MonitoringHandler;
use App\Handlers\NoticeOfAward\NoticeOfAwardHandler;
use App\Handlers\NoticeToProceed\NoticeToProceedHandler;
use App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoHandler;
use App\Handlers\PostQualification\PostQualificationHandler;
use App\Handlers\PreBidConference\PreBidConferenceDecisionHandler;
use App\Handlers\PreBidConference\PreBidConferenceDocumentsHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDecisionHandler;
use App\Handlers\PreProcurementConference\PreProcurementConferenceDocumentsHandler;
use App\Handlers\ProcurementInitiation\ProcurementInitiationHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDecisionHandler;
use App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDocumentsHandler;
use App\Http\Controllers\ProcurementController;
use App\Http\Requests\Procurement\BacResolutionDocumentRequest;
use App\Http\Requests\Procurement\BiddingDocumentsRequest;
use App\Http\Requests\Procurement\BidEvaluationDocumentsRequest;
use App\Http\Requests\Procurement\BidOpeningDocumentsRequest;
use App\Http\Requests\Procurement\CompleteProcessRequest;
use App\Http\Requests\Procurement\CompletionDocumentsRequest;
use App\Http\Requests\Procurement\MonitoringDocumentRequest;
use App\Http\Requests\Procurement\NoticeOfAwardDocumentRequest;
use App\Http\Requests\Procurement\NoticeToProceedDocumentRequest;
use App\Http\Requests\Procurement\PerformanceBondContractAndPoDocumentsRequest;
use App\Http\Requests\Procurement\PostQualificationDocumentsRequest;
use App\Http\Requests\Procurement\PreBidConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreBidConferenceDocumentsRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDecisionRequest;
use App\Http\Requests\Procurement\PreProcurementConferenceDocumentsRequest;
use App\Http\Requests\Procurement\ProcurementInitiationRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDecisionRequest;
use App\Http\Requests\Procurement\SupplementalBidBulletinDocumentsRequest;
use App\Models\User;
use App\Services\BlockchainService;
use App\Services\MultichainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Mockery\LegacyMockInterface;

uses(RefreshDatabase::class, WithFaker::class);

// Test setup
beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Create a mock for MultichainService
    $this->multichainService = Mockery::mock(MultichainService::class);
    
    // Create a mock for BlockchainService
    $this->blockchainService = Mockery::mock(BlockchainService::class);
    $this->blockchainService->shouldReceive('getClient')->andReturn($this->multichainService);
});

afterEach(function () {
    Mockery::close();
});

// Helper function to access private/protected methods for testing
function getPrivateMethod($object, $methodName)
{
    $reflection = new ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method;
}

// Helper function to create a controller instance with mocked dependencies
function createControllerInstance()
{
    $multichainService = new class extends MultichainService {
        public function __construct() {}
    };
    
    $blockchainService = Mockery::mock(BlockchainService::class);
    $blockchainService->shouldReceive('getClient')->andReturn($multichainService);
    
    return new ProcurementController($blockchainService);
}

// Update createMockHandler function to use correct namespaces
function createMockHandler($handlerClass)
{
    // Get the full class name including namespace
    $fullClassName = '\\'.ltrim($handlerClass, '\\');
    $handler = Mockery::mock($fullClassName);
    $handler->shouldReceive('handle')
        ->once()
        ->andReturn([
            'success' => true,
            'message' => 'Operation successful',
        ]);

    return $handler;
}

// Replace the skipped middleware tests with actual implementations
test('auth middleware redirects unauthenticated users', function () {
    // Register a temporary test route that uses the auth middleware
    Route::middleware(['web', 'auth'])->get('/test-auth-route', function () {
        return 'authenticated-only-content';
    })->name('test.auth.route');

    // Make a request to the route without authentication
    $response = $this->get('/test-auth-route');

    // Verify redirect to login page
    $response->assertStatus(302);
    $response->assertRedirect(route('login'));
});

test('auth middleware allows authenticated users', function () {
    // Register a temporary test route that uses the auth middleware
    Route::middleware(['web', 'auth'])->get('/test-auth-route', function () {
        return 'authenticated-only-content';
    })->name('test.auth.route');

    // Create and authenticate as a user
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get('/test-auth-route');

    // Verify successful access
    $response->assertStatus(200);
    $response->assertSee('authenticated-only-content');
});

test('middleware adds resubmission prevention headers', function () {
    // Extract the middleware closure from the ProcurementController
    $controller = createControllerInstance();
    $reflector = new ReflectionClass($controller);
    $middlewareProperty = $reflector->getProperty('middleware');
    $middlewareProperty->setAccessible(true);
    $middlewares = $middlewareProperty->getValue($controller);

    // Get the anti-resubmission middleware (the third middleware in the controller)
    $antiResubmitMiddleware = $middlewares[2]['middleware'];

    // Create a mock request and a real redirect response
    $request = Mockery::mock('Illuminate\Http\Request');
    $redirectResponse = new RedirectResponse('/somewhere');

    // Create a next callback that returns our redirect response
    $next = function ($request) use ($redirectResponse) {
        return $redirectResponse;
    };

    // Apply the middleware directly
    $response = $antiResubmitMiddleware($request, $next);

    // Now test the headers on the response returned by our middleware
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($response->headers->get('Cache-Control'))->toContain('must-revalidate');
    expect($response->headers->get('Cache-Control'))->toContain('private');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=0');

    expect($response->headers->get('Pragma'))->toBe('no-cache');
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    // Check that the Expires and Last-Modified headers are present with proper GMT format
    $expiresHeader = $response->headers->get('Expires');
    $lastModifiedHeader = $response->headers->get('Last-Modified');

    expect($expiresHeader)->toMatch('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/');
    expect($lastModifiedHeader)->toMatch('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/');
});

// Helper result processing tests
test('process handler result returns proper response for success', function () {
    $controller = createControllerInstance();

    $result = [
        'success' => true,
        'message' => 'Operation successful',
    ];

    $method = getPrivateMethod($controller, 'processHandlerResult');
    $response = $method->invoke($controller, $result);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
    expect(session('message'))->toEqual('Operation successful');
    expect(session('success'))->toBeTrue();
});

test('process handler result returns proper response for failure', function () {
    $controller = createControllerInstance();

    $result = [
        'success' => false,
        'message' => 'Operation failed',
    ];

    $method = getPrivateMethod($controller, 'processHandlerResult');
    $response = $method->invoke($controller, $result);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(url()->previous());
    expect(session('errors')->first('error'))->toEqual('Operation failed');
});

// Fixed handleProcurementAction tests using reflection
test('handleProcurementAction calls handler and processes result', function () {
    $controller = createControllerInstance();
    $request = Mockery::mock('Illuminate\Http\Request');
    $handler = Mockery::mock('App\Handlers\BaseHandler');

    $handler->shouldReceive('handle')
        ->once()
        ->with($request)
        ->andReturn(['success' => true, 'message' => 'Success!']);

    $method = getPrivateMethod($controller, 'handleProcurementAction');
    $response = $method->invokeArgs($controller, [$request, $handler]);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getSession()->get('message'))->toBe('Success!');
    expect($response->getSession()->get('success'))->toBeTrue();
});

test('handleProcurementAction processes failure result correctly', function () {
    $controller = createControllerInstance();
    $request = Mockery::mock('Illuminate\Http\Request');
    $handler = Mockery::mock('App\Handlers\BaseHandler');

    $handler->shouldReceive('handle')
        ->once()
        ->with($request)
        ->andReturn(['success' => false, 'message' => 'Operation failed']);

    $method = getPrivateMethod($controller, 'handleProcurementAction');
    $response = $method->invokeArgs($controller, [$request, $handler]);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getSession()->has('errors'))->toBeTrue();
});

test('handleProcurementAction handles exceptions from handler gracefully', function () {
    $controller = createControllerInstance();
    $request = Mockery::mock('Illuminate\Http\Request');
    $handler = Mockery::mock('App\Handlers\BaseHandler');

    $handler->shouldReceive('handle')
        ->once()
        ->with($request)
        ->andThrow(new Exception('Handler exception'));

    $method = getPrivateMethod($controller, 'handleProcurementAction');

    try {
        $method->invokeArgs($controller, [$request, $handler]);
        $this->fail('Exception should have been thrown');
    } catch (Exception $e) {
        expect($e->getMessage())->toEqual('Handler exception');
    }
});

// Tests for all controller actions
test('publishProcurementInitiation calls handler and redirects correctly', function () {
    /** @var \App\Handlers\ProcurementInitiation\ProcurementInitiationHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(ProcurementInitiationHandler::class);
    $request = new ProcurementInitiationRequest;

    $controller = createControllerInstance();
    $response = $controller->publishProcurementInitiation($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('publishPreProcurementConferenceDecision calls handler and redirects correctly', function () {
    /** @var \App\Handlers\PreProcurementConference\PreProcurementConferenceDecisionHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(PreProcurementConferenceDecisionHandler::class);
    $request = new PreProcurementConferenceDecisionRequest;

    $controller = createControllerInstance();
    $response = $controller->publishPreProcurementConferenceDecision($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadPreProcurementConferenceDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\PreProcurementConference\PreProcurementConferenceDocumentsHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(PreProcurementConferenceDocumentsHandler::class);
    $request = new PreProcurementConferenceDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadPreProcurementConferenceDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('publishPreBidConferenceDecision calls handler and redirects correctly', function () {
    /** @var \App\Handlers\PreBidConference\PreBidConferenceDecisionHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(PreBidConferenceDecisionHandler::class);
    $request = new PreBidConferenceDecisionRequest;

    $controller = createControllerInstance();
    $response = $controller->publishPreBidConferenceDecision($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadPreBidConferenceDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\PreBidConference\PreBidConferenceDocumentsHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(PreBidConferenceDocumentsHandler::class);
    $request = new PreBidConferenceDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadPreBidConferenceDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('publishSupplementalBidBulletinDecision calls handler and redirects correctly', function () {
    /** @var \App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDecisionHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(SupplementalBidBulletinDecisionHandler::class);
    $request = new SupplementalBidBulletinDecisionRequest;

    $controller = createControllerInstance();
    $response = $controller->publishSupplementalBidBulletinDecision($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadSupplementalBidBulletinDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\SupplementalBidBulletin\SupplementalBidBulletinDocumentsHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(SupplementalBidBulletinDocumentsHandler::class);
    $request = new SupplementalBidBulletinDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadSupplementalBidBulletinDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadBiddingDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\BiddingDocuments\BiddingDocumentsHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(BiddingDocumentsHandler::class);
    $request = new BiddingDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadBiddingDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadBidOpeningDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\BidOpening\BidOpeningHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(BidOpeningHandler::class);
    $request = new BidOpeningDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadBidOpeningDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadBidEvaluationDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\BidEvaluation\BidEvaluationHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(BidEvaluationHandler::class);
    $request = new BidEvaluationDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadBidEvaluationDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadPostQualificationDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\PostQualification\PostQualificationHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(PostQualificationHandler::class);
    $request = new PostQualificationDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadPostQualificationDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadBacResolutionDocument calls handler and redirects correctly', function () {
    /** @var \App\Handlers\BacResolution\BacResolutionHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(BacResolutionHandler::class);
    $request = new BacResolutionDocumentRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadBacResolutionDocument($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadNoaDocument calls handler and redirects correctly', function () {
    /** @var \App\Handlers\NoticeOfAward\NoticeOfAwardHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(NoticeOfAwardHandler::class);
    $request = new NoticeOfAwardDocumentRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadNoaDocument($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadPerformanceBondContractAndPoDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\PerformanceBondContractAndPo\PerformanceBondContractAndPoHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(PerformanceBondContractAndPoHandler::class);
    $request = new PerformanceBondContractAndPoDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadPerformanceBondContractAndPoDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadNTPDocument calls handler and redirects correctly', function () {
    /** @var \App\Handlers\NoticeToProceed\NoticeToProceedHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(NoticeToProceedHandler::class);
    $request = new NoticeToProceedDocumentRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadNTPDocument($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadMonitoringDocument calls handler and redirects correctly', function () {
    /** @var \App\Handlers\Monitoring\MonitoringHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(MonitoringHandler::class);
    $request = new MonitoringDocumentRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadMonitoringDocument($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});

test('uploadCompletionDocuments calls handler and redirects correctly', function () {
    /** @var \App\Handlers\Completion\CompletionDocumentsHandler|\Mockery\MockInterface $handler */
    $handler = createMockHandler(CompletionDocumentsHandler::class);
    $request = new CompletionDocumentsRequest;

    $controller = createControllerInstance();
    $response = $controller->uploadCompletionDocuments($request, $handler);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->headers->get('Location'))->toEqual(route('bac-secretariat.procurements-list.index'));
});
