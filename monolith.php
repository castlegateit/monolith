<?php

/**
 * Monolith
 *
 * A collection of functions and classes intended to make PHP and WordPress
 * development slightly less painful.
 *
 * @version 0.3
 */

// Do not load Monolith more than once
if (function_exists('Cgit\Monolith\Core\contains')) {
    return;
}

// Include classes
require_once __DIR__ . '/classes/autoload.php';

// Includes functions
foreach (glob(__DIR__ . '/functions/*.php') as $file) {
    require_once $file;
}
