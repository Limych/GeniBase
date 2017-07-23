<?php
namespace App;

use Gedcomx\Gedcomx;
use Gedcomx\Rs\Client\Rel;
use GeniBase\Rs\Server\GedcomxRsUpdater;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * @author Limych
 *
 */
class ApiLinksUpdater extends GedcomxRsUpdater
{

    protected $app;
    protected $request;
    protected $api_root;

    public function __construct(Application $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;

        $this->api_root = $this->request->getUriForPath($this->app["api.endpoint"].'/'.$this->app["api.version"]);
    }

    public static function update2(Application $app, Request $request, Gedcomx $document)
    {
        $visitor = new self($app, $request);
        $document->accept($visitor);
        return $document;
    }

    /**
     * @deprecated
     */
    public static function update(Gedcomx $document)
    {
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Rs\Server\GedcomxRsUpdater::visitPlaceDescription()
     */
    public function visitPlaceDescription(\Gedcomx\Conclusion\PlaceDescription $place)
    {
        $place->addLinkRelation(Rel::SELF, $this->api_root.'/places/'.$place->getId());
        $place->addLinkRelation(Rel::CHILDREN, $this->api_root.'/places/'.$place->getId().'/components');

        parent::visitPlaceDescription($place);
    }

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitEvent()
     */
    public function visitEvent(\Gedcomx\Conclusion\Event $event)
    {
        $event->addLinkRelation(Rel::SELF, $this->api_root.'/events/'.$event->getId());

        parent::visitEvent($event);
    }

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitPerson()
     */
    public function visitPerson(\Gedcomx\Conclusion\Person $person)
    {
        $person->addLinkRelation(Rel::SELF, $this->api_root.'/persons/'.$person->getId());

        parent::visitPerson($person);
    }

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitSourceDescription()
     */
    public function visitSourceDescription(\Gedcomx\Source\SourceDescription $sourceDescription)
    {
        $sourceDescription->addLinkRelation(Rel::SELF, $this->api_root.'/sources/'.$sourceDescription->getId());
        $sourceDescription->addLinkRelation(Rel::CHILDREN, $this->api_root.'/sources/'.$sourceDescription->getId().'/components');

        parent::visitSourceDescription($sourceDescription);
    }

}
