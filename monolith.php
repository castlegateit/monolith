<?php

/**
 * Monolith
 *
 * A collection of functions and classes intended to make PHP and WordPress
 * development slightly less painful.
 *
 * @version 0.6
 */

if (defined('CGIT_MONOLITH_LOADED')) {
    return;
}

require_once __DIR__ . '/classes/autoload.php';

foreach (glob(__DIR__ . '/functions/*.php') as $file) {
    require_once $file;
}

define('CGIT_MONOLITH_LOADED', true);
