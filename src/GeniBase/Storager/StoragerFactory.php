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

use GeniBase\Provider\Silex\DBaseService;

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
            case \Gedcomx\Conclusion\Conclusion::class:
                return new ConclusionStorager($dbs);

            case \Gedcomx\Source\SourceDescription::class:
                return new SourceDescriptionStorager($dbs);

            case \Gedcomx\Source\SourceReference::class:
                return new SourceReferenceStorager($dbs);

            case \Gedcomx\Conclusion\Name::class:
                return new NameStorager($dbs);

            case \Gedcomx\Conclusion\NameForm::class:
                return new NameFormStorager($dbs);

            case \Gedcomx\Conclusion\NamePart::class:
                return new NamePartStorager($dbs);

            case \Gedcomx\Conclusion\Person::class:
                return new PersonStorager($dbs);

            case \Gedcomx\Conclusion\Gender::class:
                return new GenderStorager($dbs);

            case \Gedcomx\Conclusion\PlaceDescription::class:
                return new PlaceDescriptionStorager($dbs);

            case \Gedcomx\Conclusion\Fact::class:
                return new FactStorager($dbs);

            case \Gedcomx\Conclusion\Event::class:
                return new EventStorager($dbs);

            case \Gedcomx\Conclusion\EventRole::class:
                return new EventRoleStorager($dbs);

            case \Gedcomx\Agent\Agent::class:
                return new AgentStorager($dbs);

            case \Gedcomx\Common\Attribution::class:
                return new AttributionStorager($dbs);
        }

        throw new \UnexpectedValueException('Not supported class: ' . $class);
    }
}
