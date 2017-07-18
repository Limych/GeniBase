<?php

namespace App\Controller;

use Silex\Application;
use GeniBase\Common\Statistic;

class StatisticController extends BaseController
{

    /**
     * {@inheritDoc}
     *
     * @see \App\Controller\BaseController::statistic()
     */
    public function statistic(Application $app)
    {
        $stat = new Statistic();

        $t_agents = $app['gb.db']->getTableName('agents');

        $query = "SELECT COUNT(*) AS agents FROM $t_agents";
        $result = $app['db']->fetchAssoc($query);
        if (false === $result) {
            return $result;
        }
        $stat->embed(new Statistic($result));

        $t_cons = $app['gb.db']->getTableName('conclusions');

        $query = "SELECT COUNT(*) AS conclusions, MAX(att_modified) AS conclusions_modified FROM $t_cons";
        $result = $app['db']->fetchAssoc($query);
        if (false === $result) {
            return $result;
        }
        $stat->embed(new Statistic($result));

        $t_facts = $app['gb.db']->getTableName('facts');

        $query = "SELECT COUNT(*) AS facts, MAX(att_modified) AS facts_modified FROM $t_facts AS ft " .
            "LEFT JOIN $t_cons AS cs ON ( ft.id = cs.id )";
        $result = $app['db']->fetchAssoc($query);
        if (false === $result) {
            return $result;
        }
        $stat->embed(new Statistic($result));

        return $stat;
    }
}
