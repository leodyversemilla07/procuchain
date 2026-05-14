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
        $isProduction = app()->environment('production');

        // HTTP Strict Transport Security - enforce HTTPS for 1 year
        // Only set in production to avoid issues in local development
        if ($isProduction) {
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

        $profile = $isProduction ? 'production' : 'development';
        $cspConfig = (array) config("security.csp.{$profile}", []);
        $directiveMap = [
            'script_src' => 'script-src',
            'style_src' => 'style-src',
            'img_src' => 'img-src',
            'connect_src' => 'connect-src',
            'font_src' => 'font-src',
            'worker_src' => 'worker-src',
        ];
        $directives = ["default-src 'self'"];

        foreach ($directiveMap as $configKey => $directiveName) {
            $sources = array_filter((array) ($cspConfig[$configKey] ?? []));

            if ($sources !== []) {
                $directives[] = "{$directiveName} ".implode(' ', $sources);
            }
        }

 // Allow PDF responses to be embedded in iframes on the same site.
 // The PDF viewer page uses <iframe src="blob:..."> to display documents.
 // Blob URLs are created by the page itself (same-origin) but the browser's
 // built-in PDF plugin still needs object-src and frame-src to allow blob:.
 // See: https://github.com/owncloud/web/pull/8498
 // See: https://stackoverflow.com/questions/69122526/pdf-loaded-via-embed-tag-blocked-by-frame-src-directive-in-csp
 $isPdfResponse = $response->headers->get('Content-Type') === 'application/pdf';

 if ($isPdfResponse) {
 // PDF binary responses: use minimal CSP that allows browser PDF plugin.
 // Remove X-Frame-Options (CSP frame-ancestors is the modern replacement).
 $response->headers->remove('X-Frame-Options');
 $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; object-src 'self' blob:; frame-src 'self' blob:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
 $response->headers->set('Content-Security-Policy', $csp);

 return $response;
 }

 $csp = implode('; ', [
 ...$directives,
 "object-src 'self' blob:",
 "frame-src 'self' blob:",
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
