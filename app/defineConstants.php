<?php

if ($dev_mode) {
    define('DEBUG', true);

    if (! empty($app['debug.secondary'])) {
        define('DEBUG_SECONDARY', true);
    }
    if (! empty($app['debug.profile'])) {
        define('DEBUG_PROFILE', true);
    }
}
