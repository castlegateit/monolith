<?php

/** Autoloader */
spl_autoload_register(function ($class) {
    $filename = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($filename)) {
        require_once $filename;
    }
});
