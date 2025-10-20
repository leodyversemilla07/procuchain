<?php

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\MultichainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SearchController extends Controller
{
    private array $publicContentIndex = [
        [
            'id' => 'home',
            'title' => 'Home',
            'description' => 'Welcome to ProcuChain, a blockchain-powered procurement system for transparent, efficient, and secure government document management. Transform Government Procurement Today.',
            'link' => '/',
            'keywords' => 'home, welcome, procurement, blockchain, government, transparent, secure, efficient',
        ],
        [
            'id' => 'about',
            'title' => 'About',
            'description' => 'Learn more about the ProcuChain project, its objectives, methodology, and the team behind it. Enhancing transparency and efficiency in public procurement.',
            'link' => '/about',
            'keywords' => 'about, project, objectives, methodology, team, transparency, efficiency, public procurement',
        ],
        [
            'id' => 'features',
            'title' => 'Features',
            'description' => 'Explore the key features of ProcuChain including blockchain verification, procurement tracking, audit trails, and secure role-based access control.',
            'link' => '/features',
            'keywords' => 'features, blockchain verification, procurement tracking, audit trail, security, role-based access',
        ],
        [
            'id' => 'documentation',
            'title' => 'Documentation',
            'description' => 'Access technical documentation, architecture diagrams, research papers, and user guides for the ProcuChain project.',
            'link' => '/documentation',
            'keywords' => 'documentation, technical, architecture, research papers, user guides, manuals, diagrams',
        ],
        [
            'id' => 'development',
            'title' => 'Development Process',
            'description' => 'Explore our project methodology, development timeline, and the journey from concept to completion of the ProcuChain capstone project.',
            'link' => '/development',
            'keywords' => 'development, methodology, timeline, agile, capstone project, implementation',
        ],
        [
            'id' => 'contact',
            'title' => 'Contact Us',
            'description' => 'Get in touch with the ProcuChain project team for inquiries, support, or feedback about our blockchain-powered procurement system.',
            'link' => '/contact',
            'keywords' => 'contact, support, inquiries, feedback, message, get in touch',
        ],
        [
            'id' => 'team',
            'title' => 'Our Team',
            'description' => 'Meet the team behind ProcuChain, a capstone project developed at Mindoro State University.',
            'link' => '/team',
            'keywords' => 'team, developers, contributors, researchers, capstone, students',
        ],
    ];

    /**
     * Get the role-based procurement route name for the given user role.
     *
     * @param  string  $userRole  The role of the user (e.g., 'admin', 'bac_chairman', 'hope', 'bac_secretariat')
     * @return string|null The route name or null if no matching route exists
     */
    private function getRoleBasedProcurementRoute(string $userRole): ?string
    {
        return match ($userRole) {
            'admin' => 'admin.procurements.show',
            'bac_secretariat' => 'bac-secretariat.procurements.show',
            'bac_chairman' => 'bac-chairman.procurements.show',
            'hope' => 'hope.procurements.show',
            default => null,
        };
    }

    public function index(Request $request, MultichainService $multichainService): Response
    {
        $query = $request->input('query', '');
        $publicResults = [];
        $procurementResults = [];
        $searchError = null;
        $blockchainException = null;

        if (! empty($query)) {
            try {
                $searchQueryLower = Str::lower($query);
                Log::info('SearchController: Starting search', ['query' => $query]);

                foreach ($this->publicContentIndex as $item) {
                    $searchableText = Str::lower($item['title'].' '.$item['description'].' '.$item['keywords']);
                    if (Str::contains($searchableText, $searchQueryLower)) {
                        $link = $item['link'];
                        if (substr($link, 0, 1) === '/') {
                            $url = $link;
                        } else {
                            $routeName = $item['id'] ?? null;
                            if ($routeName && app('router')->has($routeName)) {
                                $url = route($routeName);
                            } else {
                                $url = $link;
                                Log::warning('SearchController: Route not found, using direct link.', ['item_id' => $item['id'] ?? 'N/A', 'link' => $link]);
                            }
                        }

                        $publicResults[] = [
                            'id' => 'page_'.$item['id'],
                            'title' => $item['title'],
                            'description' => Str::limit($item['description'], 150),
                            'link' => $url,
                            'type' => 'Page',
                        ];
                    }
                }
                Log::info('SearchController: Public content search completed.', ['count' => count($publicResults)]);

                if (Auth::check()) {
                    Log::info('SearchController: User authenticated, attempting procurement search.');
                    /** @var User $user */
                    $user = Auth::user();
                    $userRole = $user->role;
                    $statusItems = null;

                    try {
                        Log::info('SearchController: Calling listStreamItems for status stream.');
                        // Use database cache for large blockchain data (10000 items)
                        $statusItems = Cache::store('database')->remember(
                            'search_status_items',
                            now()->addMinutes(5),
                            fn () => $multichainService->listStreamItems(StreamEnums::STATUS->value, true, 10000, 0, false)
                        );
                        Log::info('SearchController: listStreamItems call completed.', ['response_type' => gettype($statusItems)]);

                        if (! is_array($statusItems)) {
                            Log::warning('SearchController: listStreamItems did not return an array.', [
                                'query' => $query,
                                'return_type' => gettype($statusItems),
                                'return_value' => $statusItems,
                            ]);
                            throw new \RuntimeException('Blockchain service returned unexpected data type: '.gettype($statusItems));
                        }

                    } catch (Throwable $e) {
                        $blockchainException = $e;
                        Log::error('SearchController: Blockchain communication error during listStreamItems', [
                            'query' => $query,
                            'error_class' => get_class($e),
                            'error_message' => $e->getMessage(),
                        ]);
                        throw $blockchainException;
                    }

                    if (is_array($statusItems)) {
                        Log::info('SearchController: Processing status stream items.', ['count' => count($statusItems)]);
                        try {
                            $latestStatuses = collect($statusItems)
                                ->map(function ($item) {
                                    if (! isset($item['data']['json'])) {
                                        Log::debug('SearchController: Skipping item due to missing data.json', ['item_keys' => array_keys($item)]);

                                        return null;
                                    }

                                    return $item['data']['json'];
                                })
                                ->filter()
                                ->filter(function ($status) {
                                    $hasId = isset($status['procurement_id']);
                                    $hasTitle = isset($status['procurement_title']);
                                    if (! $hasId || ! $hasTitle) {
                                        Log::debug('SearchController: Skipping status due to missing fields', ['status_keys' => array_keys($status)]);
                                    }

                                    return $hasId && $hasTitle;
                                })
                                ->keyBy('procurement_id')
                                ->all();

                            Log::info('SearchController: Finished processing statuses.', ['latest_count' => count($latestStatuses)]);

                            foreach ($latestStatuses as $procurementId => $status) {
                                $stage = $status['stage'] ?? 'N/A';
                                $currentStatus = $status['current_status'] ?? 'N/A';

                                $procTitleLower = Str::lower($status['procurement_title']);
                                $procIdLower = Str::lower((string) $procurementId);

                                if (Str::contains($procTitleLower, $searchQueryLower) || Str::contains($procIdLower, $searchQueryLower)) {
                                    $routeName = $this->getRoleBasedProcurementRoute($userRole);

                                    if ($routeName) {
                                        if (app('router')->has($routeName)) {
                                            $procurementResults[] = [
                                                'id' => 'proc_'.$procurementId,
                                                'title' => $status['procurement_title']." ({$procurementId})",
                                                'description' => "Current Stage: {$stage} | Status: {$currentStatus}",
                                                'link' => route($routeName, ['id' => $procurementId]),
                                                'type' => 'Procurement',
                                            ];
                                        } else {
                                            Log::warning('SearchController: Route not found for procurement link.', ['route_name' => $routeName, 'procurement_id' => $procurementId]);
                                        }
                                    }
                                }

                                if (count($procurementResults) >= 10) {
                                    Log::info('SearchController: Procurement result limit reached.');
                                    break;
                                }
                            }
                            Log::info('SearchController: Procurement search loop completed.', ['found_count' => count($procurementResults)]);

                        } catch (Throwable $processingError) {
                            Log::error('SearchController: Error processing blockchain data', [
                                'query' => $query,
                                'error_class' => get_class($processingError),
                                'error_message' => $processingError->getMessage(),
                            ]);
                            throw $processingError;
                        }
                    }
                } else {
                    Log::info('SearchController: User not authenticated, skipping procurement search.');
                }

            } catch (Throwable $e) {
                Log::error('SearchController: Search failed. See trace below.', [
                    'query' => $query,
                    'error_class' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $searchError = 'Failed to perform search due to a server error. Please try again later.';
            }
        } else {
            Log::info('SearchController: Empty search query received.');
        }

        $results = array_slice(array_merge($procurementResults, $publicResults), 0, 15);
        Log::info('SearchController: Returning search results.', ['total_count' => count($results), 'has_error' => ! is_null($searchError)]);

        return Inertia::render('search/index', [
            'query' => $query,
            'results' => $results,
            'searchError' => $searchError,
        ]);
    }

    public function suggestions(Request $request, MultichainService $multichainService): JsonResponse
    {
        $query = $request->input('query', '');
        $suggestions = [];
        $limit = 5;

        if (empty($query)) {
            return response()->json(['suggestions' => []]);
        }

        try {
            $searchQueryLower = Str::lower($query);

            foreach ($this->publicContentIndex as $item) {
                if (count($suggestions) >= $limit) {
                    break;
                }

                $searchableText = Str::lower($item['title'].' '.$item['keywords']);
                if (Str::contains($searchableText, $searchQueryLower)) {
                    $link = $item['link'];
                    $url = (substr($link, 0, 1) === '/') ? $link : (app('router')->has($item['id']) ? route($item['id']) : $link);

                    $suggestions[] = [
                        'id' => 'page_'.$item['id'],
                        'title' => $item['title'],
                        'link' => $url,
                        'type' => 'Page',
                    ];
                }
            }

            if (Auth::check() && count($suggestions) < $limit) {
                /** @var User $user */
                $user = Auth::user();
                $userRole = $user->role;
                $statusItems = null;

                try {
                    // Use database cache for blockchain data
                    $statusItems = Cache::store('database')->remember(
                        'search_suggestions_status_items',
                        now()->addMinutes(5),
                        fn () => $multichainService->listStreamItems(StreamEnums::STATUS->value, true, 500, 0, false)
                    );

                    if (is_array($statusItems)) {
                        $latestStatuses = collect($statusItems)
                            ->map(fn ($item) => $item['data']['json'] ?? null)
                            ->filter()
                            ->filter(fn ($status) => isset($status['procurement_id']) && isset($status['procurement_title']))
                            ->keyBy('procurement_id')
                            ->reverse()
                            ->all();

                        foreach ($latestStatuses as $procurementId => $status) {
                            if (count($suggestions) >= $limit) {
                                break;
                            }

                            $procTitleLower = Str::lower($status['procurement_title']);
                            $procIdLower = Str::lower((string) $procurementId);

                            if (Str::contains($procTitleLower, $searchQueryLower) || Str::contains($procIdLower, $searchQueryLower)) {
                                $routeName = $this->getRoleBasedProcurementRoute($userRole);

                                if ($routeName && app('router')->has($routeName)) {
                                    $suggestions[] = [
                                        'id' => 'proc_'.$procurementId,
                                        'title' => Str::limit($status['procurement_title'], 40)." ({$procurementId})",
                                        'link' => route($routeName, ['id' => $procurementId]),
                                        'type' => 'Procurement',
                                    ];
                                }
                            }
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning('SearchController::suggestions: Blockchain error during procurement suggestion search.', [
                        'query' => $query,
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

        } catch (Throwable $e) {
            Log::error('SearchController::suggestions: Failed to generate suggestions.', [
                'query' => $query,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['suggestions' => []], 500);
        }

        $uniqueSuggestions = collect($suggestions)->unique('id')->values()->all();

        return response()->json(['suggestions' => $uniqueSuggestions]);
    }
}
