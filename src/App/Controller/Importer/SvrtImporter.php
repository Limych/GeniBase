<?php
namespace App\Controller\Importer;

use App\Util;
use Gedcomx\Conclusion\Event;
use Gedcomx\Conclusion\Person;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Source\SourceDescription;
use Gedcomx\Types\FactType;
use Gedcomx\Types\GenderType;
use Gedcomx\Types\NamePartType;
use Gedcomx\Types\ResourceType;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GeniBase\Types\PlaceTypes;

class SvrtImporter extends GeniBaseImporter
{

    const REFRESH_PERIOD = 120;  // seconds

    protected $cached_fpath;
    protected $imported_fpath;
    protected $count_fpath;

    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->imported_fpath = BASE_DIR . '/tmp/imported_id.txt';
        $this->cached_fpath = BASE_DIR . '/tmp/import.json';
        $this->count_fpath = BASE_DIR . '/tmp/count.txt';
    }

    public function import(Request $request)
    {
        $id = $this->loadImportedId();

        if (false === $json = $this->getData($id)) {
            return new Response(null, 204);
        }

        $overtime = false;
		$pcnt = 0;
        foreach ($json as $r) {
            if ($r->id <= $id) {
                continue;
            }
            if (defined('DEBUG_PROFILE')) {
                \App\Util\Profiler::startTimer('SvrtImport');
            }
            switch ($r->source_type_id) {
                default:
                    var_dump($r);
                    $this->saveImportedId($r->id);
                    throw new \LogicException("Undefined logic for source type " . $r->source_type_id);

                case 1:
                    $this->importKilled($r);
                    break;
            }
			$pcnt++;

            if (Util::executionTime() >= 10000) {
                $overtime = true;
                break;
            }
        }
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::dumpTimers();
        }
        $this->saveImportedId($r->id);

        $period = 0;
        if (!$overtime) {
            $this->flushCache();

            $store_fpath = $this->getStoreFPath($r->id);
            $period = (! file_exists($store_fpath) ? self::REFRESH_PERIOD : 0);
        }

        $response = new Response("<div><progress value='" . $r->id . "' max='" . $this->getCount() . "'></progress> " .
            sprintf('%d of %d records (%.2f records/sec)', $r->id, $this->getCount(), ($pcnt * 1000 / Util::executionTime())) . "</div>");
        if (! defined('DEBUG_PROFILE')) {
            $response->headers->set('Refresh', $period . '; url=' . $request->getUri());
        }

        return $response;
    }

    protected function loadImportedId()
    {
        $id = file_exists($this->imported_fpath)
            ? intval(file_get_contents($this->imported_fpath))
            : 0;
        return $id;
    }

    protected function saveImportedId($id)
    {
        if (! isset($this->imported_fpath)) {
            $this->loadImportedId();
        }

        file_put_contents($this->imported_fpath, intval($id));
    }

    /**
     *
     * @param number $id
     * @return string
     */
    protected function getStoreFPath($id)
    {
        return $this->app['svrt.1914.store'] . "/import_{$id}.json";
    }

    /**
     *
     * @param number $id
     * @return boolean|\stdClass
     */
    protected function getData($id)
    {
        $store_fpath = $this->getStoreFPath($id);

        if (file_exists($this->cached_fpath)) {
            $json = file_get_contents($this->cached_fpath);
        } else {
            if (file_exists($store_fpath)) {
                $json = file_get_contents($store_fpath);
            } elseif (! empty($key = $this->app['svrt.1914.token'])) {
                $json = file_get_contents("http://1914.svrt.ru/export.php?key=$key&id=$id");
                if (false === $json) {
                    return false;
                }

                file_put_contents($store_fpath, $json);
            }
            file_put_contents($this->cached_fpath, $json);
        }
        return json_decode($json);
    }

    protected function getCount()
    {
        static $count;

        // Check cached data
        if (! empty($count)) {
            return $count;
        }
        if (file_exists($this->count_fpath) && (filemtime($this->count_fpath) + 86400 >= time())) {
            $count = file_get_contents($this->count_fpath);
            return (false === $count ? false : (int) $count);
        }

        // Get data from source
        $contents = file_get_contents("http://1914.svrt.ru/");
        if (false === $contents || ! preg_match('/базе содержится ([\d\s]+) запись/u', $contents, $matches)) {
            return false;
        }
        $count = (int) str_replace(' ', '', $matches[1]);

        // Store data to cache
        file_put_contents($this->count_fpath, $count);

        return $count;
    }

    protected function flushCache()
    {
        @unlink($this->cached_fpath);
    }

    protected function importKilled($rec)
    {
        $this->app['locale'] = 'ru';

        $date_formal = (($rec->date_from == $rec->date_to)
            ? '+'.$rec->date_from : '+'.$rec->date_from.'/+'.$rec->date_to);

        $this->setAgent(Agents::getSvrtAgent($this->gbs));

        $place_description = join("; ", [
            'Какого уезда: ' . $rec->region,
            'Какой волости, села, деревни или станицы: ' . $rec->place,
        ]);
        $source_citation = join("; ", [
            'Звание: ' . $rec->rank,
            'Фамилия, имя и отчество: ' . trim($rec->surname . ', ' . $rec->name, ', '),
            'Какого вероисповедания: ' . $rec->religion,
            'Холост или женат: ' . $rec->marital,
            $place_description,
            'Ранен, убит, в плену или без вести пропал: ' . $rec->reason,
            'Когда, год месяц и число: ' . $rec->date,
        ]);

        $src = $this->importKilledSource($rec, $source_citation);
        $plc = $this->importKilledPlace($rec, $src);
        $psn = $this->importKilledPerson($rec, $src, $plc, $date_formal, $source_citation, $place_description);
        $evt = $this->importKilledEvent($rec, $src, $psn, $date_formal, $source_citation);
    }

    protected function importKilledSource($rec, $source_citation)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }
        $title = 'Именные списки убитым, раненым и без вести пропавшим нижним чинам (солдатам)';
        $src = $this->gbs->newStorager(SourceDescription::class)->save([
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#source_' . md5($title),
            ],
            'resourceType'  => ResourceType::COLLECTION,
            'citations' => [[
                'lang'  => 'ru',
                'value' => $title,
            ]],
            'titles' => [[
                'lang'  => 'ru',
                'value' => $title,
            ]],
        ]);

        $title = $rec->source;
        $src = $this->gbs->newStorager(SourceDescription::class)->save([
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#source_' . md5($title),
            ],
            'resourceType'  => ResourceType::PHYSICALARTIFACT,
            'citations' => [[
                'lang'  => 'ru',
                'value' => $title,
            ]],
            'titles' => [[
                'lang'  => 'ru',
                'value' => $title,
            ]],
            'componentOf' => [
                'description'   => '#' . $src->getId(),
            ],
            'sortKey'   => sprintf('%05d', $rec->source_nr),
        ]);

        $mediator_id = null;
        if (preg_match('|svrt\.ru/|', $rec->source_url)) {
            $mediator_id = Agents::getSvrtAgent($this->gbs)->getId();
        } elseif (preg_match('|rsl\.ru/|', $rec->source_url)) {
            $mediator_id = Agents::getRslAgent($this->gbs)->getId();
        }
        $citation = 'Страница ' . Util::numberFormat($this->app, $rec->source_pg, 0) . '. ' . $rec->source;
        $src = $this->gbs->newStorager(SourceDescription::class)->save([
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#source_' . md5($citation),
            ],
            'resourceType'  => ResourceType::PHYSICALARTIFACT,
            'citations' => [[
                'lang'  => 'ru',
                'value' => $citation,
            ]],
            'componentOf' => [
                'description'   => '#' . $src->getId(),
            ],
            'about'    => strtr(
                $rec->source_url,
                [   '{pg}'  => ($rec->source_pg + $rec->source_pg_corr),    ]
            ),
            'mediator'  => [
                'resourceId'    => $mediator_id,
            ],
            'sortKey'   => sprintf('%07d', $rec->source_pg),
        ]);

        $src = $this->gbs->newStorager(SourceDescription::class)->save([
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#source_' . md5($source_citation),
            ],
            'resourceType'  => ResourceType::RECORD,
            'citations' => [[
                'lang'  => 'ru',
                'value' => $source_citation,
            ]],
            'componentOf' => [
                'description'   => '#' . $src->getId(),
            ],
        ]);

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $src;
    }

    protected function importKilledPlace($rec, $src)
    {
        static $patterns, $replaces, $patterns4split;

        if (! isset($patterns)) {
            $tmp = [
                '\bгуб\.'       => 'губерния',
                '\bобл\.'       => 'область',
                '\bу\.'         => 'уезд',
                '\bв(ол)?\.'    => 'волость',
                '\bокр\.'       => 'округа',
                '\bг(ор)?\.'    => '',
                '\bмещ(\.|анин\b)?' => '',
            ];
            $patterns = array_map(
                function ($v) {
                    return "/\b$v/";
                },
                array_keys($tmp)
            );
            $replaces = array_map(
                function ($v) {
                    return " $v ";
                },
                array_values($tmp)
            );
            unset($tmp);

            $patterns[] = '/\s{2,}/';
            $replaces[] = ' ';

            $patterns4split = [
                '\s*[,;]\s*',
                '\s+(?=и с(?:ела|\.))',
                '\s+(?=и д(?:ер|\.))',
                '\s+(?=и ст\.)',
                '\s+(?=и уч\.)',
                '\s+(?:[сдпгх]|ст|уч)\.',
            ];
            $patterns4split = implode('|', $patterns4split);
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
        }

        $name = 'Российская империя';
        $plc = $this->gbs->newStorager(PlaceDescription::class)->save([
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#place_' . md5($name),
            ],
            'names' => [[
                'lang'  => 'ru',
                'value' => $name,
            ]],
            'type'  => PlaceTypes::COUNTRY,
            'temporalDescription'   => [
                'original'  => '1721—1917',
                'formal'    => '+1721-11-02/+1917-09-14',
            ],
        ]);

        $segments = preg_split("/(?:$patterns4split)/iu", $rec->region . ', ' . $rec->place, null, PREG_SPLIT_NO_EMPTY);
        $segments = array_values(array_filter(array_map(function ($v) use ($patterns, $replaces) {
            if (preg_match("/\bген\.\-губ\.|\bнам\./", $v)) {
                return '';
            }
            return trim(preg_replace($patterns, $replaces, $v), "\x00..\x1F ,;");
        }, $segments)));
        $max = count($segments) - 1;
        $place_path = $name;
        for ($i = 0; $i <= $max; $i++) {
            $place_path .= ' > ' . $segments[$i];
            $data = [
                'identifiers'   => [
                    \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#place_' . md5($place_path),
                ],
                'extracted' => true,
                'names' => [[
                    'lang'  => 'ru',
                    'value' => $segments[$i],
                ]],
                'jurisdiction'  => [
                    'resourceId'    => $plc->getId(),
                ],
//                 'confidence'    => \Gedcomx\Types\ConfidenceLevel::LOW,
            ];
            if ($i === $max) {
                $data['sources']   = [[
                    'description'   => '#' . $src->getId(),
                ]];
            }
            $plc = $this->gbs->newStorager(PlaceDescription::class)->save($data);
        }

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $plc;
    }

    protected function importKilledPerson($rec, $src, $plc, $date_formal, $source_citation, $place_description)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
            \App\Util\Profiler::omitSubtimers();
        }
        $psn = $this->gbs->newStorager(Person::class)->save(
            [
                'extracted' => true,
                'identifiers'   => [
                    \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#person_' . md5($source_citation),
                ],
                'living'    => false,
                'gender'    => [
                    'type'  => GenderType::MALE,
                ],
                'names' => [[
                    'date'  => [
                        'original'  => $rec->date,
                        'formal'    => $date_formal,
                    ],
                    'nameForms' => [[
                        'lang'      => 'ru',
                        'fullText'  => trim($rec->surname . ', ' . $rec->name, ', '),
                        'parts'     => [[
                            'type'  => NamePartType::SURNAME,
                            'value' => $rec->surname,
                        ]],
                    ]],
                    'sources'   => [[
                        'description'   => '#' . $src->getId(),
                    ]],
                ]],
                'facts' => [[
                    'type'  => FactType::BIRTH,
                    'place' => [
                        'original'      => $place_description,
                        'description'   => '#' . $plc->getId(),
                    ],
                    'sources'   => [[
                        'description'   => '#' . $src->getId(),
                    ]],
                ]],
                'sources'   => [[
                    'description'   => '#' . $src->getId(),
                ]],
            ],
            null,
            [
                'makeId_name'   => "Person-1: $source_citation",
            ]
        );

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $psn;
    }

    protected function importKilledEvent($rec, $src, $psn, $date_formal, $source_citation)
    {
        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::startTimer(__METHOD__);
            \App\Util\Profiler::omitSubtimers();
        }
        switch ($rec->reason) {
            case 'Убит':
            case 'Пропал без вести':
                $event_type = \Gedcomx\Types\EventType::DEATH;
                break;
            default:
                $event_type = \Gedcomx\Types\EventType::MILITARYDISCHARGE;
                break;
        }

        $evt = $this->gbs->newStorager(Event::class)->save(
            [
                'extracted' => true,
                'identifiers'   => [
                    \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#event_' . md5($source_citation),
                ],
                'type'  => $event_type,
                'date'  => [
                    'original'  => $rec->date,
                    'formal'    => $date_formal,
                ],
                'roles' => [[
                    'type'      => \Gedcomx\Types\EventRoleType::PRINCIPAL,
                    'person'    => [
                        'resourceId'    => $psn->getId(),
                    ],
                    'details'   => $rec->reason,
                ]],
                'sources'   => [[
                    'description'   => '#' . $src->getId(),
                ]],
            ],
            null,
            [
                'makeId_name'   => "Event-1: $source_citation",
            ]
        );

        if (defined('DEBUG_PROFILE')) {
            \App\Util\Profiler::stopTimer(__METHOD__);
        }
        return $evt;
    }
}
