<?php

declare(strict_types=1);

/**
 * Backward-compatible shim for deployments still pointing at index.php.
 *
 * New web server examples should target switchboard.php directly so the PHP
 * script name is not confused with the public routing model.
 */

require __DIR__ . '/switchboard.php';
