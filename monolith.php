<?php

/**
 * Monolith
 *
 * A collection of functions and classes intended to make PHP and WordPress
 * development slightly less painful.
 *
 * @version 0.1
 */

// Include classes
require_once __DIR__ . '/classes/autoload.php';

// Includes functions
foreach (glob(__DIR__ . '/functions/*.php') as $file) {
    require_once $file;
}
