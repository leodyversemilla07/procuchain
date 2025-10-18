<?php

use App\Services\DeviceDetectionService;
use Jenssegers\Agent\Agent;

describe('DeviceDetectionService', function () {
    beforeEach(function () {
        $this->service = new DeviceDetectionService;
    });

    describe('getDeviceType', function () {
        test('it detects desktop devices', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('isTablet')->andReturn(false);
            $agent->shouldReceive('isMobile')->andReturn(false);
            $agent->shouldReceive('isDesktop')->andReturn(true);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getDeviceType())->toBe('Desktop');
        });

        test('it detects mobile devices', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('isTablet')->andReturn(false);
            $agent->shouldReceive('isMobile')->andReturn(true);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getDeviceType())->toBe('Mobile');
        });

        test('it detects tablet devices', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('isTablet')->andReturn(true);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getDeviceType())->toBe('Tablet');
        });

        test('it returns Unknown for unrecognized devices', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('isTablet')->andReturn(false);
            $agent->shouldReceive('isMobile')->andReturn(false);
            $agent->shouldReceive('isDesktop')->andReturn(false);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getDeviceType())->toBe('Unknown');
        });

        test('it prioritizes tablet over mobile detection', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('isTablet')->andReturn(true);
            // Should not reach isMobile check
            $agent->shouldNotReceive('isMobile');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getDeviceType())->toBe('Tablet');
        });
    });

    describe('getBrowser', function () {
        test('it detects browser with version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('browser')->andReturn('Chrome');
            $agent->shouldReceive('version')->with('Chrome')->andReturn('120.0.0.0');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getBrowser())->toBe('Chrome 120.0.0.0');
        });

        test('it detects browser without version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('browser')->andReturn('Firefox');
            $agent->shouldReceive('version')->with('Firefox')->andReturn(false);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getBrowser())->toBe('Firefox');
        });

        test('it handles Safari browser', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('browser')->andReturn('Safari');
            $agent->shouldReceive('version')->with('Safari')->andReturn('17.2');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getBrowser())->toBe('Safari 17.2');
        });

        test('it handles Edge browser', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('browser')->andReturn('Edge');
            $agent->shouldReceive('version')->with('Edge')->andReturn('120.0.2210.91');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getBrowser())->toBe('Edge 120.0.2210.91');
        });

        test('it handles null version gracefully', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('browser')->andReturn('Chrome');
            $agent->shouldReceive('version')->with('Chrome')->andReturn(null);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getBrowser())->toBe('Chrome');
        });

        test('it handles empty string version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('browser')->andReturn('Chrome');
            $agent->shouldReceive('version')->with('Chrome')->andReturn('');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getBrowser())->toBe('Chrome');
        });
    });

    describe('getPlatform', function () {
        test('it detects Windows platform with version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('Windows');
            $agent->shouldReceive('version')->with('Windows')->andReturn('10.0');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('Windows 10.0');
        });

        test('it detects macOS platform with version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('OS X');
            $agent->shouldReceive('version')->with('OS X')->andReturn('10.15.7');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('OS X 10.15.7');
        });

        test('it detects Linux platform', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('Linux');
            $agent->shouldReceive('version')->with('Linux')->andReturn(false);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('Linux');
        });

        test('it detects iOS platform with version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('iOS');
            $agent->shouldReceive('version')->with('iOS')->andReturn('17.2.1');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('iOS 17.2.1');
        });

        test('it detects Android platform with version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('AndroidOS');
            $agent->shouldReceive('version')->with('AndroidOS')->andReturn('14.0');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('AndroidOS 14.0');
        });

        test('it handles platform without version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('Ubuntu');
            $agent->shouldReceive('version')->with('Ubuntu')->andReturn(false);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('Ubuntu');
        });

        test('it handles null version gracefully', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('Windows');
            $agent->shouldReceive('version')->with('Windows')->andReturn(null);

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('Windows');
        });

        test('it handles empty string version', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('platform')->andReturn('Windows');
            $agent->shouldReceive('version')->with('Windows')->andReturn('');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getPlatform())->toBe('Windows');
        });
    });

    describe('integration scenarios', function () {
        test('it can detect complete device information', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('isTablet')->andReturn(false);
            $agent->shouldReceive('isMobile')->andReturn(false);
            $agent->shouldReceive('isDesktop')->andReturn(true);
            $agent->shouldReceive('browser')->andReturn('Chrome');
            $agent->shouldReceive('version')->with('Chrome')->andReturn('120.0.0.0');
            $agent->shouldReceive('platform')->andReturn('Windows');
            $agent->shouldReceive('version')->with('Windows')->andReturn('10.0');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getDeviceType())->toBe('Desktop');
            expect($service->getBrowser())->toBe('Chrome 120.0.0.0');
            expect($service->getPlatform())->toBe('Windows 10.0');
        });

        test('it can detect mobile device with complete information', function () {
            $agent = Mockery::mock(Agent::class);
            $agent->shouldReceive('isTablet')->andReturn(false);
            $agent->shouldReceive('isMobile')->andReturn(true);
            $agent->shouldReceive('browser')->andReturn('Safari');
            $agent->shouldReceive('version')->with('Safari')->andReturn('17.2');
            $agent->shouldReceive('platform')->andReturn('iOS');
            $agent->shouldReceive('version')->with('iOS')->andReturn('17.2.1');

            $service = new DeviceDetectionService;
            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('agent');
            $property->setAccessible(true);
            $property->setValue($service, $agent);

            expect($service->getDeviceType())->toBe('Mobile');
            expect($service->getBrowser())->toBe('Safari 17.2');
            expect($service->getPlatform())->toBe('iOS 17.2.1');
        });
    });
});
