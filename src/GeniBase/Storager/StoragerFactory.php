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
namespace GeniBase\Storager;

use Gedcomx\Agent\Agent;
use Gedcomx\Conclusion\Conclusion;
use Gedcomx\Conclusion\Event;
use Gedcomx\Conclusion\EventRole;
use Gedcomx\Conclusion\Fact;
use Gedcomx\Conclusion\Gender;
use Gedcomx\Conclusion\Identifier;
use Gedcomx\Conclusion\Name;
use Gedcomx\Conclusion\NameForm;
use Gedcomx\Conclusion\NamePart;
use Gedcomx\Conclusion\Person;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Source\SourceDescription;
use Gedcomx\Source\SourceReference;
use GeniBase\DBase\DBaseService;

/**
 *
 * @author Limych
 */
class StoragerFactory
{

    /**
     *
     * @param DBaseService $dbs
     * @param mixed        $class
     *
     * @throws \UnexpectedValueException
     */
    public static function newStorager(DBaseService $dbs, $class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        switch ($class) {
            case Conclusion::class:
                return new ConclusionStorager($dbs);

            case SourceDescription::class:
                return new SourceDescriptionStorager($dbs);

            case SourceReference::class:
                return new SourceReferenceStorager($dbs);

            case Name::class:
                return new NameStorager($dbs);

            case NameForm::class:
                return new NameFormStorager($dbs);

            case NamePart::class:
                return new NamePartStorager($dbs);

            case Person::class:
                return new PersonStorager($dbs);

            case Gender::class:
                return new GenderStorager($dbs);

            case PlaceDescription::class:
                return new PlaceDescriptionStorager($dbs);

            case Fact::class:
                return new FactStorager($dbs);

            case Event::class:
                return new EventStorager($dbs);

            case EventRole::class:
                return new EventRoleStorager($dbs);

            case Agent::class:
                return new AgentStorager($dbs);

            case Identifier::class:
                return new IdentifierStorager($dbs);
        }

        throw new \UnexpectedValueException('Not supported class: ' . $class);
    }
}
