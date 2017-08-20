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
namespace GeniBase\Rs\Server;

use Gedcomx\Gedcomx;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Gedcomx\Conclusion\Fact;
use GeniBase\Storager\GeniBaseStorager;

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

//         $this->api_root = $this->request->getUriForPath($this->app["api.endpoint"].'/'.$this->app["api.version"]);
        $this->api_root = '';   // FIXME
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
        // Do nothing
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Rs\Server\GedcomxRsUpdater::visitPlaceDescription()
     */
    public function visitPlaceDescription(\Gedcomx\Conclusion\PlaceDescription $place)
    {
        $place->addLinkRelation(Rel::SELF, $this->api_root.'/places/'.$place->getId());
        $place->addLinkRelation(Rel::CHILDREN, $this->api_root.'/places/'.$place->getId().'/components');
        if (! empty($res = $place->getJurisdiction())) {
            $place->addLinkRelation(Rel::PARENT_RELATIONSHIPS, $this->api_root.'/places/'.$res->getResourceId());
        }

        parent::visitPlaceDescription($place);
    }

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitFact()
     */
    public function visitFact(Fact $fact)
    {
        if (! empty($rel = $fact->getPlace())) {
            $fact->addLinkRelation(
                Rel::PLACE_DESCRIPTION,
                $this->api_root.'/places/' . GeniBaseStorager::getIdFromReference($rel->getDescriptionRef())
            );
        }

        parent::visitFact($fact);
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

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitSourceReference()
     */
    public function visitSourceReference(\Gedcomx\Source\SourceReference $sourceReference)
    {
        $sourceReference->addLinkRelation(
            Rel::DESCRIPTION,
            $this->api_root.'/sources/' . GeniBaseStorager::getIdFromReference($sourceReference->getDescriptionRef())
        );

        parent::visitSourceReference($sourceReference);
    }

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitAttribution()
     */
    protected function visitAttribution(\Gedcomx\Common\Attribution $attribution)
    {
        if (! empty($res = $attribution->getContributor())) {
            /** @var \Gedcomx\Links\HypermediaEnabledData $entity */
            $entity = end($this->contextStack);
            $entity->addLinkRelation(Rel::CONTRIBUTOR, $this->api_root.'/agents/'.$res->getResourceId());
        }

        parent::visitAttribution($attribution);
    }
}
