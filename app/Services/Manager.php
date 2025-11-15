<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Class alias for Wayfinder compatibility
 *
 * This is a class_alias registration file that redirects App\Services\Manager
 * to the actual App\Libraries\MultiChain\Manager implementation.
 *
 * Wayfinder tries to resolve Manager from App\Services, but the actual
 * implementation lives in App\Libraries\MultiChain.
 */

// Register the class alias
class_alias(
    \App\Libraries\MultiChain\Manager::class,
    Manager::class
);
