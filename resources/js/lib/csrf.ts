/**
 * Read the XSRF-TOKEN cookie that Laravel sets on every response.
 * This is the correct CSRF mechanism for Inertia apps — the <meta name="csrf-token">
 * tag rendered by Blade goes stale after any Inertia navigation, causing 419 errors.
 *
 * The cookie is URI-encoded; Laravel's VerifyCsrfToken expects it decoded in the
 * X-XSRF-TOKEN header.
 *
 * @see https://inertiajs.com/csrf-protection
 */
export function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
