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

use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Common\Statistic;
use GeniBase\DBase\GeniBaseInternalProperties;
use GeniBase\Rs\Server\GedcomxRsFilter;
use GeniBase\Rs\Server\GedcomxRsUpdater;
use GeniBase\Storager\StoragerFactory;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Rs\ApiLinksUpdater;

class PlacesController extends BaseController
{

    /**
     * {@inheritDoc}
     *
     * @see \App\Controller\BaseController::statistic()
     */
    public function statistic(Application $app)
    {
        $t_places = $app['gb.db']->getTableName('places');

        $query = "SELECT COUNT(*) AS places, MAX(att_modified) AS places_modified FROM $t_places";

        $result = $app['db']->fetchAssoc($query);

        if (false !== $result) {
            $result = new Statistic($result);
        }

        return $result;
    }

    public static function bindRoutes($app, $base)
    {
        /** @var Application $app */
        $app->get($base, "places.controller:showPlace")->bind('places-root');
        $app->get($base.'/{id}', "places.controller:showPlace")->bind('place');
    }

    public static function bindApiRoutes($app, $base)
    {
        /** @var ControllerCollection $app */
        $app->get($base, "places.controller:getComponents");
        $app->get($base.'/{id}', "places.controller:getOne");
        $app->get($base.'/{id}/components', "places.controller:getComponents");
        //         $app->post($base, "places.controller:save");
        //         $app->put($base.'/{id}', "places.controller:update");
        //         $app->delete($base.'/{id}', "places.controller:delete");
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
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], PlaceDescription::class)
        ->loadGedcomx(
            [
            'id' => $id,
            ]
        );

        if (false === $gedcomx || empty($gedcomx->toArray())) {
            return new Response(null, 204);
        }

        ApiLinksUpdater::update2($app, $request, $gedcomx);

        if (class_exists(WebLinkExtension::class)) {
            (new WebLinkExtension($app['request_stack']))->link($request->getUri(), Rel::DESCRIPTION);
        }
        return $gedcomx;
    }

    /**
     *
     * @param string $id
     * @return array|Response
     */
    public function getComponents(Application $app, Request $request, $id = null)
    {
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], PlaceDescription::class)
        ->loadComponentsGedcomx([     'id' => $id     ]);

        if (false === $gedcomx || empty($gedcomx->toArray())) {
            return new Response(null, 204);
        }

        ApiLinksUpdater::update2($app, $request, $gedcomx);

        return $gedcomx;
    }

    /**
     * @todo
     */
    //     public function save(Request $request)
    //     {

    //         $note = $this->getDataFromRequest($request);
    //         return array("id" => $this['service']->save($note));

    //     }

    /**
     * @todo
     */
    //     public function update($id, Request $request)
    //     {
    //         $note = $this->getDataFromRequest($request);
    //         $this['service']->update($id, $note);
    //         return $note;

    //     }

    /**
     * @todo
     */
    //     public function delete($id)
    //     {

    //         return $this['service']->delete($id);

    //     }

    //     public function getDataFromRequest(Request $request)
    //     {
    //         return $note = array(
    //             "note" => $request->request->get("note")
    //         );
    //     }

    /**
     *
     * @param Application $app
     * @param Request     $request
     * @param string      $id
     * @return \Symfony\Component\HttpFoundation\Response|\Gedcomx\Gedcomx
     */
    public function showPlace(Application $app, Request $request, $id = null)
    {
        $storager = StoragerFactory::newStorager($app['gb.db'], PlaceDescription::class);
        if (empty($id)) {
            $gedcomx = $storager->loadComponentsGedcomx([]);
        } else {
            $gedcomx = $storager->loadGedcomx([
                'id' => $id,
            ]);
        }

        if (false === $gedcomx || empty($gedcomx->toArray())) {
            $app->abort(404);
        }

        GedcomxRsUpdater::update($gedcomx);

        if (empty($id)) {
            return $app['twig']->render(
                'places_list.html.twig',
                [
                    'gedcomx' => $gedcomx,
                ]
            );
        } else {
            $gedcomx2 = $storager->loadComponentsGedcomx([  'id' => $gedcomx->getPlaces()[0]->getId()   ]);
            GedcomxRsUpdater::update($gedcomx2);

            $gedcomx3 = GedcomxRsFilter::filter(
                $storager->loadNeighboringPlacesGedcomx($gedcomx->getPlaces()[0]),
                $gedcomx,
                $gedcomx2
            );
            GedcomxRsUpdater::update($gedcomx3);

            return $app['twig']->render(
                'place.html.twig',
                [
                    'gedcomx' => $gedcomx,
                    'components' => $gedcomx2->getPlaces(),
                    'neighbors' => $gedcomx3->getPlaces(),
                    'map_zoom'  => GeniBaseInternalProperties::getPropertyOf($gedcomx->getPlaces()[0], '_zoom'),
                ]
            );
        }
    }
}
