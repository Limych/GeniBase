<?php
namespace GeniBase\Storager;

use Gedcomx\Agent\Agent;
use Gedcomx\Conclusion\Conclusion;
use Gedcomx\Conclusion\Name;
use Gedcomx\Source\SourceReference;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\Event;
use Gedcomx\Conclusion\EventRole;
use Gedcomx\Conclusion\Fact;
use Gedcomx\Conclusion\Identifier;
use Gedcomx\Conclusion\Person;
use Gedcomx\Conclusion\Gender;
use Gedcomx\Conclusion\NameForm;
use Gedcomx\Conclusion\NamePart;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Source\SourceDescription;
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
    static function newStorager(DBaseService $dbs, $class)
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

            case DateInfo::class:
                return new DateInfoStorager($dbs);

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
