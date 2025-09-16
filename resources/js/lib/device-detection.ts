/**
 * Enhanced client-side OS detection utilities
 * Provides more accurate Windows 11 detection than server-side User Agent parsing
 */

// Type definitions for User Agent Data API
interface NavigatorUABrandVersion {
    brand: string;
    version: string;
}

interface NavigatorUAData {
    brands: NavigatorUABrandVersion[];
    mobile: boolean;
    platform: string;
    platformVersion?: string;
}

interface NavigatorWithUAData extends Navigator {
    userAgentData?: NavigatorUAData;
}

export interface OSInfo {
    platform: string;
    version?: string;
    isWindows11?: boolean;
}

/**
 * Detect Windows 11 using client-side APIs
 */
export function detectWindowsVersion(): OSInfo {
    const platform = navigator.platform;
    const userAgent = navigator.userAgent;

    // Default platform detection
    const osInfo: OSInfo = {
        platform: platform.includes('Win') ? 'Windows' : platform
    };

    // Enhanced Windows 11 detection using multiple indicators
    if (platform.includes('Win') || userAgent.includes('Windows')) {
        osInfo.platform = 'Windows';

        // Try to detect Windows 11 using various methods
        const isWindows11 = detectWindows11();

        if (isWindows11) {
            osInfo.version = '11';
            osInfo.isWindows11 = true;
            osInfo.platform = 'Windows 11';
        } else if (userAgent.includes('Windows NT 10.0')) {
            osInfo.version = '10';
            osInfo.platform = 'Windows 10';
        }
    }

    return osInfo;
}

/**
 * Advanced Windows 11 detection using multiple client-side indicators
 */
function detectWindows11(): boolean {
    try {
        // Method 1: Check for Navigator User Agent Data (Chromium browsers)
        if ('userAgentData' in navigator) {
            const nav = navigator as NavigatorWithUAData;
            const userAgentData = nav.userAgentData;
            if (userAgentData && userAgentData.platform) {
                // Windows 11 may report specific platform version
                if (userAgentData.platform === 'Windows' && userAgentData.platformVersion) {
                    // Windows 11 typically reports version 13.0.0 or higher
                    const version = parseFloat(userAgentData.platformVersion);
                    if (version >= 13.0) {
                        return true;
                    }
                }
            }
        }

        // Method 2: Check screen resolution typical of Windows 11
        const screenWidth = screen.width;
        const screenHeight = screen.height;
        const pixelRatio = window.devicePixelRatio;

        // Windows 11 common resolutions and high DPI support
        const commonWin11Resolutions = [
            { width: 1920, height: 1080 },
            { width: 2560, height: 1440 },
            { width: 3840, height: 2160 },
            { width: 1366, height: 768 }
        ];

        const hasCommonWin11Resolution = commonWin11Resolutions.some(
            res => Math.abs(screenWidth - res.width) <= 50 && Math.abs(screenHeight - res.height) <= 50
        );

        // Method 3: Check for modern browser features typically available on Windows 11
        const hasModernFeatures =
            'serviceWorker' in navigator &&
            'webkitRequestFullscreen' in document.documentElement &&
            CSS.supports('backdrop-filter', 'blur(10px)') &&
            'IntersectionObserver' in window;

        // Method 4: Check User Agent for Windows 11 specific patterns
        const userAgent = navigator.userAgent;
        const hasWin11Indicators =
            userAgent.includes('Windows NT 10.0') &&
            (userAgent.includes('WebView/3.0') || // Edge WebView in Windows 11
                userAgent.includes('Chrome/1') && parseInt(userAgent.match(/Chrome\/(\d+)/)?.[1] || '0') >= 96);

        // Method 5: Check for Windows 11 specific browser versions
        const chromeVersion = userAgent.match(/Chrome\/(\d+)/)?.[1];
        const firefoxVersion = userAgent.match(/Firefox\/(\d+)/)?.[1];
        const edgeVersion = userAgent.match(/Edg\/(\d+)/)?.[1];

        const hasModernBrowser =
            (chromeVersion && parseInt(chromeVersion) >= 96) ||
            (firefoxVersion && parseInt(firefoxVersion) >= 94) ||
            (edgeVersion && parseInt(edgeVersion) >= 96);

        // Combine indicators to make an educated guess
        const indicators = [
            hasCommonWin11Resolution && pixelRatio >= 1,
            hasModernFeatures,
            hasWin11Indicators,
            hasModernBrowser
        ];

        const positiveIndicators = indicators.filter(Boolean).length;

        // If we have 2 or more positive indicators, likely Windows 11
        return positiveIndicators >= 2;

    } catch (error) {
        console.warn('Error detecting Windows 11:', error);
        return false;
    }
}

/**
 * Get comprehensive device information for login tracking
 */
export function getDeviceInfo() {
    const osInfo = detectWindowsVersion();

    return {
        platform: osInfo.platform,
        userAgent: navigator.userAgent,
        screenResolution: `${screen.width}x${screen.height}`,
        pixelRatio: window.devicePixelRatio,
        language: navigator.language,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        cookieEnabled: navigator.cookieEnabled,
        onlineStatus: navigator.onLine,
        ...(osInfo.isWindows11 && { detectedWindows11: true })
    };
}
