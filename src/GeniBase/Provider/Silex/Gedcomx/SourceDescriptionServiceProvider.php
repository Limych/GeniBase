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
namespace GeniBase\Provider\Silex\Gedcomx;

use Pimple\Container;
use GeniBase\Rs\Server\ApiLinksUpdater;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Common\Statistic;
use GeniBase\Storager\SourceDescriptionStorager;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 *
 * @package GeniBase
 * @subpackage Silex
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class SourceDescriptionServiceProvider extends GedcomxServiceProvider
{

    /**
     * {@inheritDoc}
     * @see \Pimple\ServiceProviderInterface::register()
     */
    public function register(Container $app)
    {
        parent::register($app);

        $app['source_description.controller'] = $this;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Provider\Silex\Gedcomx\GedcomxServiceProvider::statistic()
     */
    public function statistic(Application $app)
    {
        $t_srcs = $app['gb.db']->getTableName('sources');
        $query = "SELECT COUNT(*) AS sources, MAX(att_modified) AS sources_modified FROM $t_srcs";
        $result = $app['db']->fetchAssoc($query);
        if (false !== $result) {
            $result = new Statistic($result);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Provider\Silex\Gedcomx\GedcomxServiceProvider::mountApiRoutes()
     */
    public function mountApiRoutes($app, $base)
    {
        $app->get($base, "source_description.controller:getComponents");
        $app->get($base.'/{id}', "source_description.controller:getOne");
        $app->get($base.'/{id}/components', "source_description.controller:getComponents");
//         $app->post($base, "places.controller:save");
//         $app->put($base.'/{id}', "places.controller:update");
//         $app->delete($base.'/{id}', "places.controller:delete");
    }

    /**
     *
     * @param string $id
     * @return array|Response
     */
    public function getOne(Application $app, Request $request, $id)
    {
        $st = new SourceDescriptionStorager($app['gb.db']);
        $gedcomx = $st->loadGedcomx(array( 'id' => $id ));

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

    /**
     *
     * @param string $id
     * @return array|Response
     */
    public function getComponents(Application $app, Request $request, $id = null)
    {
        $st = new SourceDescriptionStorager($app['gb.db']);
        $gedcomx = $st->loadComponentsGedcomx(array( 'id' => $id ));

        if (false === $gedcomx || empty($gedcomx->toArray())) {
            return new Response(null, 204);
        }

        ApiLinksUpdater::update2($app, $request, $gedcomx);

        return $gedcomx;
    }

//     /**
//      *
//      * @param \Symfony\Component\HttpFoundation\Request $request
//      * @return array
//      */
//     public function save(Request $request)
//     {
//         // TODO
//         foreach ($request->request->get('sourceDescriptions') as $source) {
//             $this['service']->save(new SourceDescription($source));
//         }

//         return new Response(null, 204);
//     }

//     /**
//      *
//      * @param string $id
//      * @param \Symfony\Component\HttpFoundation\Request $request
//      * @return array
//      * @todo
//      */
//     public function update($id, Request $request)
//     {
//         $note = $this->getDataFromRequest($request);
//         $this['service']->update($id, $note);
//         return $note;

//     }

//     /**
//      * @todo
//      */
//     public function delete($id)
//     {

//         return $this['service']->delete($id);

//     }

//     public function getDataFromRequest(Request $request)
//     {
//         return $note = [
//             "note" => $request->request->get("note")
//         ];
//     }
}
