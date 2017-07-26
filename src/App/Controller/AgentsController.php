<?php

namespace App\Controller;

use App\Rs\ApiLinksUpdater;
use Gedcomx\Agent\Agent;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Common\Statistic;
use GeniBase\Storager\StoragerFactory;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentsController extends BaseController
{

    /**
     * {@inheritDoc}
     *
     * @see \App\Controller\BaseController::statistic()
     */
    public function statistic(Application $app)
    {
        $t_agents = $app['gb.db']->getTableName('agents');

        $result = $app['db']->fetchAssoc("SELECT COUNT(*) AS agents FROM $t_agents");
        if (false !== $result) {
            $result = new Statistic($result);
        }

        return $result;
    }

    public static function bindApiRoutes($app, $base)
    {
        $app->get($base, "agents.controller:statistic");
        $app->get($base.'/{id}', "agents.controller:getOne");
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
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], Agent::class)
            ->loadGedcomx([     'id' => $id,    ]);

        if (false === $gedcomx || empty($gedcomx->toArray())) {
            return new Response(null, 204);
        }

        ApiLinksUpdater::update2($app, $request, $gedcomx);

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
