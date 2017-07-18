<?php

namespace App\Controller;

use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Common\Statistic;
use GeniBase\Rs\Server\GedcomxRsFilter;
use GeniBase\Rs\Server\GedcomxRsUpdater;
use GeniBase\Storager\StoragerFactory;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $t_cons = $app['gb.db']->getTableName('conclusions');

        $query = "SELECT COUNT(*) AS places, MAX(att_modified) AS places_modified FROM $t_places AS pl " .
            "LEFT JOIN $t_cons AS cs ON ( pl.id = cs.id )";

        $result = $app['db']->fetchAssoc($query);

        if (false !== $result) {
            $result = new Statistic($result);
        }

        return $result;
    }

    public static function bindRoutes($app, $base)
    {
        /**
 * @var Application $app
*/
        $app->get($base, "places.controller:showPlace");
        $app->get($base.'/{id}', "places.controller:showPlace")->bind('place');
    }

    public static function bindApiRoutes($app, $base)
    {
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

        if (false === $gedcomx) {
            return new Response(null, 204);
        }

        GedcomxRsUpdater::update($gedcomx);

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
    public function getComponents(Application $app, $id = null)
    {
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], PlaceDescription::class)
        ->loadListGedcomx(
            [
            'id' => $id,
            ],
            null,
            'loadCompanions=0'
        );

        if (false === $gedcomx) {
            return new Response(null, 204);
        }

        GedcomxRsUpdater::update($gedcomx);

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
            $gedcomx = $storager->loadListGedcomx(
                [
                'id' => $id,
                ]
            );
        } else {
            $gedcomx = $storager->loadGedcomx(
                [
                'id' => $id,
                ]
            );
        }

        if (false === $gedcomx) {
            return new Response(null, 204);
        }

        GedcomxRsUpdater::update($gedcomx);

        $gedcomx2 = $storager->loadListGedcomx(
            [
            'id' => $gedcomx->getPlaces()[0]->getId(),
            ],
            null,
            'loadCompanions=0'
        );

        $gedcomx3 = GedcomxRsFilter::filter(
            $storager->loadNeighboringPlacesGedcomx($gedcomx->getPlaces()[0], 'loadCompanions=0'),
            $gedcomx,
            $gedcomx2
        );

        return $app['twig']->render(
            'place.html.twig',
            [
            'gedcomx' => $gedcomx,
            'components' => $gedcomx2->getPlaces(),
            'neighbors' => $gedcomx3->getPlaces(),
            ]
        );
    }
}
