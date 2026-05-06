<?php

namespace App\Http\Middleware;

use App\Services\BlockedIpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckBlockedIp
{
    public function __construct(
        private BlockedIpService $blockedIpService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ipAddress = $request->ip();

        if ($this->blockedIpService->isBlocked($ipAddress)) {
            Log::warning('Blocked IP attempted access', [
                'ip_address' => $ipAddress,
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'Your IP address has been blocked. Please contact the administrator if you believe this is an error.');
        }

        return $next($request);
    }
}
