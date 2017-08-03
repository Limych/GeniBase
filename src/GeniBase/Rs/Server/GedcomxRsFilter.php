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
use Gedcomx\Agent\Agent;
use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\Document;
use Gedcomx\Conclusion\Event;
use Gedcomx\Conclusion\Person;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Conclusion\Relationship;
use Gedcomx\Records\Collection;
use Gedcomx\Records\Field;
use Gedcomx\Records\RecordDescriptor;
use Gedcomx\Rt\GedcomxModelVisitorBase;
use Gedcomx\Source\SourceDescription;

/**
 *
 * @author Limych
 */
class GedcomxRsFilter extends GedcomxModelVisitorBase
{

    const MODE_COLLECT_IDS  = '\GeniBase\Rs\Server\GedcomxRsFilter:ModeCollectIds';
    const MODE_FILTER_NODES = '\GeniBase\Rs\Server\GedcomxRsFilter:ModeFilterNodes';

    protected $mode;

    protected $supplier_ids;

    protected $filtered;

    public function __construct()
    {
        $this->supplier_ids = [];
        $this->filtered = new Gedcomx();
    }

    /**
     * @param Gedcomx $supplier
     */
    protected function startCollecting(Gedcomx $supplier)
    {
        $this->mode = self::MODE_COLLECT_IDS;

        $supplier->accept($this);
    }

    /**
     * @param Gedcomx $document
     */
    protected function startFiltering(Gedcomx $document)
    {
        $this->mode = self::MODE_FILTER_NODES;

        $document->accept($this);
    }

    /**
     * @return Gedcomx $filtered
     */
    public function getFiltered()
    {
        return $this->filtered;
    }

    /**
     *
     * @param Gedcomx $document
     * @param Gedcomx $supplier
     * @return Gedcomx
     */
    public static function filter(Gedcomx $document, Gedcomx $supplier)
    {
        $visitor = new self();

        $args = func_get_args();
        array_shift($args);

        foreach ($args as $supp) {
            $visitor->startCollecting($supp);
        }
        $visitor->startFiltering($document);

        return $visitor->getFiltered();
    }

    protected function passFilter(ExtensibleData $entity)
    {
        if ($this->mode === self::MODE_COLLECT_IDS) {
            if (! empty($id = $entity->getId())) {
                $this->supplier_ids[] = $id;
            }
        } elseif ($this->mode === self::MODE_FILTER_NODES) {
            if (empty($id = $entity->getId()) || ! in_array($id, $this->supplier_ids)) {
                return true;
            }
        }
        return false;
    }

    public function visitDocument(Document $document)
    {
        if ($this->passFilter($document)) {
            $this->filtered->addDocument($document);
        }
        parent::visitDocument($document);
    }

    public function visitPlaceDescription(PlaceDescription $place)
    {
        if ($this->passFilter($place)) {
            $this->filtered->addPlace($place);
        }
        parent::visitPlaceDescription($place);
    }

    public function visitEvent(Event $event)
    {
        if ($this->passFilter($event)) {
            $this->filtered->addEvent($event);
        }
        parent::visitEvent($event);
    }

    public function visitAgent(Agent $agent)
    {
        if ($this->passFilter($agent)) {
            $this->filtered->addAgent($agent);
        }
        parent::visitAgent($agent);
    }

    public function visitSourceDescription(SourceDescription $sourceDescription)
    {
        if ($this->passFilter($sourceDescription)) {
            $this->filtered->addSourceDescription($sourceDescription);
        }
        parent::visitSourceDescription($sourceDescription);
    }

    public function visitCollection(Collection $collection)
    {
        if ($this->passFilter($collection)) {
            $this->filtered->addCollection($collection);
        }
        parent::visitCollection($collection);
    }

    public function visitRecordDescriptor(RecordDescriptor $recordDescriptor)
    {
        if ($this->passFilter($recordDescriptor)) {
            $this->filtered->addRecordDescriptor($recordDescriptor);
        }
        parent::visitRecordDescriptor($recordDescriptor);
    }

    public function visitField(Field $field)
    {
        if ($this->passFilter($field)) {
            $this->filtered->addField($field);
        }
        parent::visitField($field);
    }

    public function visitRelationship(Relationship $relationship)
    {
        if ($this->passFilter($relationship)) {
            $this->filtered->addRelationship($relationship);
        }
        parent::visitRelationship($relationship);
    }

    public function visitPerson(Person $person)
    {
        if ($this->passFilter(Person)) {
            $this->filtered->addPerson(Person);
        }
        parent::visitPerson($person);
    }
}
