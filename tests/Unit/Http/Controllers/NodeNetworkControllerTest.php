<?php

use App\Http\Controllers\NodeNetworkController;

describe('NodeNetworkController::maskIp()', function () {
    it('masks public IPv4 address preserving first octet', function () {
        $controller = new NodeNetworkController;
        $method = new ReflectionMethod($controller, 'maskIp');

        expect($method->invoke($controller, '32.196.225.21'))->toBe('32.xxx.xxx.xxx');
    });

    it('masks private IPv4 address preserving first octet', function () {
        $controller = new NodeNetworkController;
        $method = new ReflectionMethod($controller, 'maskIp');

        expect($method->invoke($controller, '172.31.13.41'))->toBe('172.xxx.xxx.xxx');
    });

    it('masks localhost as 127.xxx.xxx.xxx', function () {
        $controller = new NodeNetworkController;
        $method = new ReflectionMethod($controller, 'maskIp');

        expect($method->invoke($controller, '127.0.0.1'))->toBe('127.xxx.xxx.xxx');
    });

    it('handles 10.x private addresses', function () {
        $controller = new NodeNetworkController;
        $method = new ReflectionMethod($controller, 'maskIp');

        expect($method->invoke($controller, '10.0.0.5'))->toBe('10.xxx.xxx.xxx');
    });
});
