<?php

namespace App\Controller;

use App\ApiLinksUpdater;
use Gedcomx\Conclusion\Person;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Common\Statistic;
use GeniBase\Storager\StoragerFactory;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonsController extends BaseController
{

    /**
     * {@inheritDoc}
     *
     * @see \App\Controller\BaseController::statistic()
     */
    public function statistic(Application $app)
    {
        $t_cons = $app['gb.db']->getTableName('conclusions');
        $t_persons = $app['gb.db']->getTableName('persons');

        $query = "SELECT COUNT(*) AS persons, MAX(att_modified) AS persons_modified FROM $t_persons AS ps " .
            "LEFT JOIN $t_cons AS cs ON ( ps.id = cs.id )";

        $result = $app['db']->fetchAssoc($query);

        if (false !== $result) {
            $result = new Statistic($result);
        }

        return $result;
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
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], Person::class)
        ->loadGedcomx(
            [
            'id' => $id,
            ]
        );

        if (false === $gedcomx) {
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
