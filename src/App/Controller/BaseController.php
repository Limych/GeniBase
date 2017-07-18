<?php

namespace App\Controller;

use Pimple\Container;
use Silex\Application;
use GeniBase\Common\Statistic;

class BaseController extends Container
{

    /**
     *
     * @param mixed  $app
     * @param string $base
     */
    public static function bindRoutes($app, $base)
    {
    }  // Empty

    /**
     *
     * @param mixed  $app
     * @param string $base
     */
    public static function bindApiRoutes($app, $base)
    {
    }  // Empty

    /**
     *
     * @param Application $app
     * @return Statistic
     */
    public function statistic(Application $app)
    {
    }  // Empty
}
