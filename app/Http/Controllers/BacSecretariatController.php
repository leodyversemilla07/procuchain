<?php

namespace App\Http\Controllers;

use App\Services\MultichainService;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Exception;

class BacSecretariatController extends BaseController
{
    private $multiChain;

    private const STREAM_DOCUMENTS = 'procurement.documents';

    private const STREAM_STATE = 'procurement.state';

    private const STREAM_EVENTS = 'procurement.events';

    public function __construct(MultichainService $multiChain)
    {
        $this->multiChain = $multiChain;
        $this->middleware('role:bac_secretariat');
        $this->middleware('auth');
    }

    public function index()
    {
        return Inertia::render('bac-secretariat/dashboard');
    }

    public function prInitiation()
    {
        return Inertia::render('bac-secretariat/procurement-phase/pr-initiation');
    }

    /**
     * Show the pre-procurement document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showPreProcurementUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct state
            if ($latestState['data']['current_state'] !== 'Pre-Procurement Conference Held') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for pre-procurement document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
            ];

            // Fix: Use the correct component path - adjust to match the actual location of your component
            return Inertia::render('bac-secretariat/procurement-phase/pre-procurement-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load pre-procurement upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading pre-procurement upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the bid invitation document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showBidInvitationUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            if (
                $phaseIdentifier !== 'Bid Invitation' ||
                ($currentState !== 'Pre-Procurement Skipped' && $currentState !== 'Pre-Procurement Completed')
            ) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for bid invitation document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Ensure we're using the correct component path
            return Inertia::render('bac-secretariat/procurement-phase/bid-invitation-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load bid invitation upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid invitation upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the bid submission document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showBidSubmissionUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            if ($phaseIdentifier !== 'Bid Opening' || $currentState !== 'Bid Invitation Published') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for bid submission and opening');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Ensure we're using the correct component path
            return Inertia::render('bac-secretariat/procurement-phase/bid-submission-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load bid submission upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid submission upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the bid evaluation document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showBidEvaluationUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            if ($phaseIdentifier !== 'Bid Evaluation' || $currentState !== 'Bids Opened') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for bid evaluation upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Ensure we're using the correct component path
            return Inertia::render('bac-secretariat/procurement-phase/bid-evaluation-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load bid evaluation upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading bid evaluation upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the post-qualification document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showPostQualificationUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Post-Qualification upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState
            ]);

            if ($phaseIdentifier !== 'Post-Qualification' || $currentState !== 'Bids Evaluated') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for post-qualification document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Render the post-qualification upload component
            return Inertia::render('bac-secretariat/procurement-phase/post-qualification-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load post-qualification upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading post-qualification upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the BAC Resolution document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showBacResolutionUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing BAC Resolution upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState
            ]);

            if ($phaseIdentifier !== 'BAC Resolution' || $currentState !== 'Post-Qualification Verified') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for BAC Resolution document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Render the BAC Resolution upload component
            return Inertia::render('bac-secretariat/procurement-phase/bac-resolution-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load BAC Resolution upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading BAC Resolution upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the Notice of Award document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showNoaUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Notice of Award upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState
            ]);

            if ($phaseIdentifier !== 'Notice Of Award' || $currentState !== 'Resolution Recorded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Notice of Award document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Render the NOA upload component
            return Inertia::render('bac-secretariat/procurement-phase/noa-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Notice of Award upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Notice of Award upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the Performance Bond document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showPerformanceBondUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Performance Bond upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState
            ]);

            if ($phaseIdentifier !== 'Performance Bond' || $currentState !== 'Awarded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Performance Bond document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Render the Performance Bond upload component
            return Inertia::render('bac-secretariat/procurement-phase/performance-bond-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Performance Bond upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Performance Bond upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the Contract and PO document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showContractPOUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Contract and PO upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState
            ]);

            if ($phaseIdentifier !== 'Contract And PO' || $currentState !== 'Performance Bond Recorded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Contract and PO document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Render the Contract and PO upload component
            return Inertia::render('bac-secretariat/procurement-phase/contract-po-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Contract and PO upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Contract and PO upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the Notice to Proceed document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showNTPUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase and state
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';
            $currentState = $latestState['data']['current_state'] ?? '';

            Log::info('Showing Notice to Proceed upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier,
                'current_state' => $currentState
            ]);

            if ($phaseIdentifier !== 'Notice To Proceed' || $currentState !== 'Contract And PO Recorded') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not eligible for Notice to Proceed document upload');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Render the NTP upload component
            return Inertia::render('bac-secretariat/procurement-phase/ntp-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Notice to Proceed upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Notice to Proceed upload form: ' . $e->getMessage());
        }
    }

    /**
     * Show the Monitoring document upload form.
     *
     * @param string $id
     * @return \Inertia\Response
     */
    public function showMonitoringUpload($id)
    {
        try {
            // Get procurement details
            $allStates = $this->multiChain->listStreamItems(self::STREAM_STATE, true, 1000, -1000);

            // Filter to get only states for this procurement
            $procurementStates = collect($allStates)
                ->map(function ($item) {
                    $data = $item['data'];
                    return [
                        'data' => $data,
                        'procurementId' => $data['procurement_id'] ?? ''
                    ];
                })
                ->filter(function ($mappedItem) use ($id) {
                    return $mappedItem['procurementId'] === $id;
                })
                ->sortByDesc(function ($item) {
                    return $item['data']['timestamp'] ?? '';
                });

            if ($procurementStates->isEmpty()) {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'Procurement not found');
            }

            $latestState = $procurementStates->first();

            // Check if the procurement is in the correct phase
            $phaseIdentifier = $latestState['data']['phase_identifier'] ?? '';

            Log::info('Showing Monitoring upload form', [
                'procurement_id' => $id,
                'phase_identifier' => $phaseIdentifier
            ]);

            if ($phaseIdentifier !== 'Monitoring') {
                return redirect()->route('bac-secretariat.procurements-list.index')
                    ->with('error', 'This procurement is not in the Monitoring phase');
            }

            $procurement = [
                'id' => $id,
                'title' => $latestState['data']['procurement_title'] ?? 'Unknown',
                'current_state' => $latestState['data']['current_state'] ?? '',
                'phase_identifier' => $latestState['data']['phase_identifier'] ?? '',
            ];

            // Render the Monitoring upload component
            return Inertia::render('bac-secretariat/procurement-phase/monitoring-upload', [
                'procurement' => $procurement,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to load Monitoring upload form:', [
                'procurement_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('bac-secretariat.procurements-list.index')
                ->with('error', 'Error loading Monitoring upload form: ' . $e->getMessage());
        }
    }
}
