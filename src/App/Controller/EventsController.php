<?php

namespace App\Controller;

use Gedcomx\Conclusion\Event;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Common\Statistic;
use GeniBase\Storager\StoragerFactory;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EventsController extends BaseController
{

    /**
     * {@inheritDoc}
     *
     * @see \App\Controller\BaseController::statistic()
     */
    public function statistic(Application $app)
    {
        $t_cons = $app['gb.db']->getTableName('conclusions');
        $t_events = $app['gb.db']->getTableName('events');

        $query = "SELECT COUNT(*) AS events, MAX(att_modified) AS events_modified FROM $t_events AS ev " .
        "LEFT JOIN $t_cons AS cs ON ( ev.id = cs.id )";
        $result = $app['db']->fetchAssoc($query);
        if (false !== $result) {
            $result = new Statistic($result);
        }

        return $result;
    }

    public static function bindApiRoutes($app, $base)
    {
        $app->get($base, "events.controller:statistic");
        $app->get($base.'/{id}', "events.controller:getOne");
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
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], Event::class)
        ->loadGedcomx(
            [
            'id' => $id,
            ]
        );

        if (false === $gedcomx) {
            return new Response(null, 204);
        }

        if (class_exists(WebLinkExtension::class)) {
            (new WebLinkExtension($app['request_stack']))->link($request->getUri(), Rel::DESCRIPTION);
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
