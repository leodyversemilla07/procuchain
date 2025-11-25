<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 *
 * Adds comprehensive security headers to all HTTP responses including:
 * - Content Security Policy (CSP)
 * - HTTP Strict Transport Security (HSTS)
 * - X-Frame-Options (Clickjacking protection)
 * - X-Content-Type-Options (MIME-sniffing protection)
 * - Referrer-Policy
 * - Permissions-Policy
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers to the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // HTTP Strict Transport Security - enforce HTTPS for 1 year
        // Only set in production to avoid issues in local development
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Clickjacking protection - only allow framing from same origin
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer policy - send full URL on same-origin, origin only on cross-origin
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');

        // Content Security Policy - allows inline scripts and styles for Inertia/Vite
        // Adjust these directives based on your specific requirements
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:", // unsafe-inline/eval needed for Vite HMR
            "style-src 'self' 'unsafe-inline' https:", // unsafe-inline needed for Tailwind
            "img-src 'self' data: https: blob:", // blob: for PDF viewer
            "font-src 'self' data: https:",
            "connect-src 'self' https: wss:", // wss: for WebSockets if used
            "worker-src 'self' blob:", // blob: for PDF.js worker, 'self' for local worker
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Permissions Policy - restrict powerful browser features
        $permissionsPolicy = implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
        ]);
        $response->headers->set('Permissions-Policy', $permissionsPolicy);

        // X-XSS-Protection - legacy but still useful for older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
