<?php
namespace App\Controller;

use App\ApiLinksUpdater;
use Gedcomx\Rs\Client\Rel;
use Gedcomx\Source\SourceDescription;
use GeniBase\Common\Statistic;
use GeniBase\Storager\StoragerFactory;
use Silex\Application;
use Symfony\Bridge\Twig\Extension\WebLinkExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SourcesController extends BaseController
{

    /**
     * {@inheritDoc}
     *
     * @see \App\Controller\BaseController::statistic()
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

    public static function bindApiRoutes($app, $base)
    {
        $app->get($base, "sources.controller:getComponents");
        $app->get($base.'/{id}', "sources.controller:getOne");
        $app->get($base.'/{id}/components', "sources.controller:getComponents");
        $app->post($base, "places.controller:save");
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
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], SourceDescription::class)
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

    /**
     *
     * @param string $id
     * @return array|Response
     */
    public function getComponents(Application $app, Request $request, $id = null)
    {
        $gedcomx = StoragerFactory::newStorager($app['gb.db'], SourceDescription::class)
            ->loadListGedcomx([     'id' => $id     ]);

        if (false === $gedcomx) {
            return new Response(null, 204);
        }

        ApiLinksUpdater::update2($app, $request, $gedcomx);

        return $gedcomx;
    }

    /**
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return array
     */
    public function save(Request $request)
    {
        // TODO
        //         foreach ($request->request->get('sourceDescriptions') as $source) {
        //             $this['service']->save(new SourceDescription($source));
        //         }

        return new Response(null, 204);
    }

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

    /**
     * @todo
     */
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
