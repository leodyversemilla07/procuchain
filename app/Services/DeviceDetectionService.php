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
        return $platform.($version ? " {$version}" : '');
    }
}
