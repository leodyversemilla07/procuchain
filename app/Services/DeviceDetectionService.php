<?php

namespace App\Services;

use Jenssegers\Agent\Agent;

class DeviceDetectionService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent;
    }

    public function getDeviceType(): string
    {
        if ($this->agent->isTablet()) {
            return 'Tablet';
        } elseif ($this->agent->isMobile()) {
            return 'Mobile';
        } elseif ($this->agent->isDesktop()) {
            return 'Desktop';
        }

        return 'Unknown';
    }

    public function getBrowser(): string
    {
        $browser = $this->agent->browser();
        $version = $this->agent->version($browser);

        return $browser.($version ? " {$version}" : '');
    }

    public function getPlatform(): string
    {
        $platform = $this->agent->platform();
        $version = $this->agent->version($platform);
        $userAgent = request()->userAgent() ?? '';

        // Enhanced Windows 11 detection
        if ($platform === 'Windows' && $this->isWindows11($userAgent)) {
            return 'Windows 11';
        }

        return $platform.($version ? " {$version}" : '');
    }

    /**
     * Detect Windows 11 using various indicators
     */
    protected function isWindows11(string $userAgent): bool
    {
        // Check for Windows 11 specific patterns in User Agent
        $windows11Patterns = [
            // Edge on Windows 11 often includes "Windows NT 10.0; Win64; x64; WebView/3.0"
            '/Windows NT 10\.0.*WebView\/3\.0/',
            // Some browsers include "Windows 11" explicitly
            '/Windows 11/',
            // Chrome 110+ on Windows 11 may include specific patterns
            '/Windows NT 10\.0.*Chrome\/1[1-9][0-9]\./',
        ];

        foreach ($windows11Patterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        // Additional checks for Windows NT 10.0 that might be Windows 11
        if (preg_match('/Windows NT 10\.0/', $userAgent)) {
            // Check for modern browser versions that are more likely to be on Windows 11
            $modernBrowserPatterns = [
                '/Chrome\/([0-9]+)\./' => 96,  // Chrome 96+ likely on Windows 11
                '/Firefox\/([0-9]+)\./' => 94, // Firefox 94+ likely on Windows 11
                '/Edge\/([0-9]+)\./' => 96,    // Edge 96+ likely on Windows 11
            ];

            foreach ($modernBrowserPatterns as $pattern => $minVersion) {
                if (preg_match($pattern, $userAgent, $matches)) {
                    $version = (int) $matches[1];
                    if ($version >= $minVersion) {
                        // Additional indicators that suggest Windows 11
                        if (strpos($userAgent, 'Win64') !== false &&
                            strpos($userAgent, 'x64') !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
