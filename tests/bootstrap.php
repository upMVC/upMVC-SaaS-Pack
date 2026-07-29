<?php

/**
 * PHPUnit bootstrap for the upMVC SaaS pack.
 *
 * The pack is a library, so there is no application around it. UPMVC_APP_ROOT
 * is pointed at the pack itself, which gives Application::getInstance() a
 * stable root to resolve app-relative paths against during tests.
 */

declare(strict_types=1);

if (!defined('UPMVC_APP_ROOT')) {
    define('UPMVC_APP_ROOT', dirname(__DIR__));
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
