<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 * @copyright Copyright (C) 2014-2017 Andrey Khrolenok
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 * @link https://github.com/Limych/GeniBase
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */
namespace App\Controller;

use GeniBase\Rs\Server\ApiLinksUpdater;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Common\Statistic;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GeniBase\Storager\PersonStorager;

class PersonsController extends BaseController
{

    /**
     * {@inheritDoc}
     *
     * @see \App\Controller\BaseController::statistic()
     */
    public function statistic(Application $app)
    {
        $t_persons = $app['gb.db']->getTableName('persons');
        $t_facts = $app['gb.db']->getTableName('facts');

        $stat = new Statistic();

        $query = "SELECT COUNT(*) AS persons, MAX(att_modified) AS persons_modified FROM $t_persons";
        $result = $app['db']->fetchAssoc($query);

        if (false !== $result) {
            $stat->embed(new Statistic($result));
        }

        $query = "SELECT COUNT(*) AS facts, MAX(att_modified) AS facts_modified FROM $t_facts";
        $result = $app['db']->fetchAssoc($query);

        if (false !== $result) {
            $stat->embed(new Statistic($result));
        }

        return $stat;
    }

    public static function bindApiRoutes($app, $base)
    {
        $app->get($base, "persons.controller:statistic");
        $app->get($base.'/{id}', "persons.controller:getOne");
        //         $app->post($base, "persons.controller:save");
        //         $app->put($base.'/{id}', "persons.controller:update");
        //         $app->delete($base.'/{id}', "persons.controller:delete");
    }

    /**
     *
     * @param Application $app
     * @param Request     $request
     * @param string      $id
     * @return \Symfony\Component\HttpFoundation\Response|\Gedcomx\Gedcomx
     */
    public function getOne(Application $app, Request $request, $id)
    {
        $st = new PersonStorager($app['gb.db']);
        $gedcomx = $st->loadGedcomx(array( 'id' => $id, ));

        if (false === $gedcomx || empty($gedcomx->toArray())) {
            return new Response(null, 204);
        }

        ApiLinksUpdater::update2($app, $request, $gedcomx);

        if (class_exists('Symfony\Bridge\Twig\Extension\WebLinkExtension')) {
            $wl = new WebLinkExtension($app['request_stack']);
            $wl->link($request->getUri(), Rel::DESCRIPTION);
        }
        return $gedcomx;
    }

    //     public function save(Request $request)
    //     {}  // TODO

    //     public function update($id, Request $request)
    //     {}  // TODO

    //     public function delete($id)
    //     {}  // TODO

    //     public function getDataFromRequest(Request $request)
    //     {
    //         return $note = array(
    //             "note" => $request->request->get("note")
    //         );
    //     }
}
