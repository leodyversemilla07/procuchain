<?php

describe('Basic Unit Tests', function () {
    it('validates that true is true', function () {
        expect(true)->toBeTrue()
            ->and(false)->toBeFalse()
            ->and(1)->toBe(1)
            ->and('test')->toBeString();
    });
});
