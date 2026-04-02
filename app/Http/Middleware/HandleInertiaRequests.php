<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();
        $primaryRole = $user?->getPrimaryRole();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $primaryRole,
                    'avatar' => $user->avatar ?? '',
                    'blockchain_address' => $user->blockchain_address,
                ] : null,
                'role' => $primaryRole,
                'can' => [
                    'manageProcurement' => $user?->canManageProcurement() ?? false,
                    'approveProcurement' => $user?->canApproveProcurement() ?? false,
                    'manageDocuments' => $user?->canManageDocuments() ?? false,
                    'viewDocuments' => $user?->canViewDocuments() ?? false,
                    'manageStages' => $user?->canManageStages() ?? false,
                    'accessBlockchain' => $user?->canAccessBlockchain() ?? false,
                    'manageUsers' => $user?->canManageUsers() ?? false,
                ],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
