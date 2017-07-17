<?php

error_reporting(E_ALL);

// Disable garbage collection
// https://scrutinizer-ci.com/blog/composer-gc-performance-who-is-affected-too
gc_disable();

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', __DIR__ . '/../../../vendor/autoload.php');
}

require_once PHPUNIT_COMPOSER_INSTALL;

require_once __DIR__ . '/PHPUnitUtil.php';
