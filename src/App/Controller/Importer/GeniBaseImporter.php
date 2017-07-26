<?php
namespace App\Controller\Importer;

use GeniBase\Storager\GeniBaseStorager;
use Silex\Application;

/**
 *
 * @author Limych
 *
 */
class GeniBaseImporter
{

    protected $app;
    protected $gbs;
    protected $agent;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->gbs = new GeniBaseStorager($app['gb.db']);
        $this->setAgent(Agents::getGeniBaseAgent($this->gbs));
    }

    protected function setAgent($agent)
    {
        $this->agent = $agent;
        $this->app['gb.db']->setAgent($this->agent);
    }
}
