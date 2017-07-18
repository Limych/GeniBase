<?php
namespace App\Controller;

use App\Util;
use Gedcomx\Agent\Agent;
use Gedcomx\Conclusion\Event;
use Gedcomx\Conclusion\Person;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Source\SourceDescription;
use Gedcomx\Types\FactType;
use Gedcomx\Types\GenderType;
use Gedcomx\Types\NamePartType;
use Gedcomx\Types\ResourceType;
use GeniBase\Storager\GeniBaseStorager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SvrtImporterController
{

    const REFRESH_PERIOD = 180;  // seconds

    protected $app;
    protected $cached_fpath;
    protected $imported_fpath;
    protected $gbs;
    protected $agent;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->gbs = new GeniBaseStorager($app['gb.db']);

        $this->imported_fpath = BASE_DIR . '/tmp/imported_id.txt';
        $this->cached_fpath = BASE_DIR . '/tmp/import.json';
    }

    public function import(Request $request)
    {
        $id = $this->loadImportedId();

        if (false === $json = $this->getData($id)) {
            return new Response(null, 204);
        }

        $overtime = false;
        foreach ($json as $r) {
            if ($r->id <= $id) {
                continue;
            }
            switch ($r->source_type_id) {
                default:
                    var_dump($r);
                    $this->saveImportedId($r->id);
                    throw new \LogicException("Undefined logic for source type " . $r->source_type_id);

                case 1:
                    $this->import_killed($r);
                    break;
            }

            $this->saveImportedId($r->id);
            if (! Util::isRemainingExecutionTimeBiggerThan()) {
                $overtime = true;
                break;
            }
        }

        if (! $overtime) {
            $this->flushCache();

            $url = $this->app['url_generator']->generate('api_statistic');
            $subRequest = Request::create($url);
            $response = $this->app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

            $store_fpath = $this->getStoreFPath($r->id);
            $period = (! file_exists($store_fpath) ? self::REFRESH_PERIOD : 0);
        } else {
            $response = new Response(null, 204);
            $period = 0;
        }

        $response->headers->set('Refresh', $period . '; url=' . $request->getUri());

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
            } else {
                $key = $this->app['svrt.1914.token'];
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

    protected function flushCache()
    {
        @unlink($this->cached_fpath);
    }

    public static function getSvrtAgent($gbs)
    {
        return $gbs->newStorager(Agent::class)->save(
            [
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://www.svrt.ru/',
            ],
            'homepage'  => [   'resource'  => 'http://www.svrt.ru/',    ],
            'emails'    => [
                [   'resource'  => 'mailto:svrtinfo@mail.ru',       ],
                [   'resource'  => 'mailto:bibikov2002@mail.ru',    ],
                [   'resource'  => 'mailto:strigan1@yandex.ru',     ],
                [   'resource'  => 'mailto:n-lobodina@mail.ru',     ],
            ],
            'phones'    => [
                [   'resource'  => 'tel:+7-925-367-25-95',  ],
            ],
            'names'    => [
                [
                    'lang'  => 'ru',
                    'value' => 'НП "Союз Возрождения Родословных Традиций" (СВРТ)',
                ],
            ],
            'addresses'    => [
                [
                    'country' => 'Russia',
                    'postalCode' => '121096',
                    'city' => 'г.Москва',
                    'street' => '2-я Филевская ул., д.5, к.2',
                ],
            ],
            ]
        );
    }

    public static function getRslAgent($gbs)
    {
        return $gbs->newStorager(Agent::class)->save(
            [
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://www.rsl.ru/',
            ],
            'homepage'  => [   'resource'  => 'http://www.rsl.ru/',    ],
            'emails'    => [
                [   'resource'  => 'mailto:nbros@rsl.ru',   ],
            ],
            'phones'    => [
                [   'resource'  => 'tel:+7-800-100-57-90',          ],
                [   'resource'  => 'tel:+7-499-557-04-70;ext=2068', ],
                [   'resource'  => 'tel:+7-495-695-57-90',          ],
                [   'resource'  => 'tel:+7-495-690-60-62',          ],
            ],
            'names'    => [
                [
                    'lang'  => 'ru',
                    'value' => 'Федеральное государственное бюджетное учреждение «Российская государственная библиотека» (ФГБУ «РГБ»)',
                ],
            ],
            'addresses'    => [
                [
                    'country' => 'Russia',
                    'postalCode' => '119019',
                    'city' => 'г.Москва',
                    'street' => 'ул. Воздвиженка, 3/5',
                ],
            ],
            ]
        );
    }

    protected function setAgent($agent)
    {
        $this->agent = $agent;
        $this->app['gb.db']->setAgent($this->agent);
    }

    protected function import_killed($r)
    {
        static $patterns, $replaces;

        if (! isset($patterns)) {
            $tmp = [
                'губ\.'         => 'губерния',
                'обл\.'         => 'область',
                'у\.'           => 'уезд',
                'в(ол)?\.'      => 'волость',
                'окр\.'         => 'округа',
                'г(ор)?\.'      => '',
                'д(ер)?\.'      => 'деревня',
                'с(ел)?\.'      => 'село',
                'п(ос)?\.'      => 'посёлок',
                'х(ут)?\.'      => 'хутор',
                'сл\.'          => 'слобода',
            ];
            $patterns = array_map(
                function ($v) {
                    return '/\b' . $v . '/';
                },
                array_keys($tmp)
            );
            $replaces = array_map(
                function ($v) {
                    return ' ' . $v . ' ';
                },
                array_values($tmp)
            );
            unset($tmp);

            $patterns[] = '/\s{2,}/';
            $replaces[] = ' ';
        }

        $app = $this->app;

        $app['locale'] = 'ru';

        $date_formal = (($r->date_from == $r->date_to)
            ? '+'.$r->date_from : '+'.$r->date_from.'/+'.$r->date_to);

        $agent_svrt = $this->getSvrtAgent($this->gbs);
        $agent_rsl  = $this->getRslAgent($this->gbs);

        $this->setAgent($agent_svrt);

        /// Sources ///////////////////////////////////////////////////////////////

        $title = 'Именные списки убитым, раненым и без вести пропавшим нижним чинам (солдатам)';
        $src = $this->gbs->newStorager(SourceDescription::class)->save(
            [
            'resourceType'  => ResourceType::COLLECTION,
            'citations' => [[
                'lang'  => 'ru',
                'value' => $title,
            ]],
            'titles' => [[
                'lang'  => 'ru',
                'value' => $title,
            ]],
            ]
        );

        $title = $r->source;
        $src = $this->gbs->newStorager(SourceDescription::class)->save(
            [
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
            'sortKey'   => sprintf('%05d', $r->source_nr),
            ]
        );

        $mediator_id = null;
        if (preg_match('|svrt\.ru/|', $r->source_url)) {
            $mediator_id = $agent_svrt->getId();
        } elseif (preg_match('|rsl\.ru/|', $r->source_url)) {
            $mediator_id = $agent_rsl->getId();
        }
        $src = $this->gbs->newStorager(SourceDescription::class)->save(
            [
            'resourceType'  => ResourceType::PHYSICALARTIFACT,
            'citations' => [[
                'lang'  => 'ru',
                'value' => 'Страница ' . Util::number_format($app, $r->source_pg, 0) . '. ' . $r->source,
            ]],
            'componentOf' => [
                'description'   => '#' . $src->getId(),
            ],
            'about'    => strtr(
                $r->source_url,
                array(
                '{pg}'  => ($r->source_pg + $r->source_pg_corr),
                )
            ),
            'mediator'  => [
                'resourceId'    => $mediator_id,
            ],
            'sortKey'   => sprintf('%07d', $r->source_pg),
            ]
        );

        $place_description = join(
            "; ",
            [
            'Какого уезда: ' . $r->region,
            'Какой волости, села, деревни или станицы: ' . $r->place,
            ]
        );
        $source_citation = join(
            "; ",
            [
            'Звание: ' . $r->rank,
            'Фамилия, имя и отчество: ' . trim($r->surname . ', ' . $r->name, ', '),
            'Какого вероисповедания: ' . $r->religion,
            'Холост или женат: ' . $r->marital,
            $place_description,
            'Ранен, убит, в плену или без вести пропал: ' . $r->reason,
            'Когда, год месяц и число: ' . $r->date,
            ]
        );
        $src = $this->gbs->newStorager(SourceDescription::class)->save(
            [
            'resourceType'  => ResourceType::RECORD,
            'citations' => [[
                'lang'  => 'ru',
                'value' => $source_citation,
            ]],
            'componentOf' => [
                'description'   => '#' . $src->getId(),
            ],
            ]
        );

        /// Places ///////////////////////////////////////////////////////////////

        $name = 'Российская империя';
        $plc = $this->gbs->newStorager(PlaceDescription::class)->save(
            [
            'names' => [[
                'lang'  => 'ru',
                'value' => $name,
            ]],
            'temporalDescription'   => [
                'original'  => '1721—1917',
                'formal'    => '+1721-11-02/+1917-09-14',
            ],
            ]
        );

        foreach (explode(', ', $r->region . ', ' . $r->place) as $rgn) {
            if (preg_match('/ген\.\-губ\.|нам\./', $rgn)) {
                continue;
            }

            $name = trim(preg_replace($patterns, $replaces, $rgn), "\x00..\x1F ,;");

            if (empty($name)) {
                continue;
            }

            $plc = $this->gbs->newStorager(PlaceDescription::class)->save(
                [
                'extracted' => true,
                'names' => [[
                    'lang'  => 'ru',
                    'value' => $name,
                ]],
                'jurisdiction'  => [
                    'resourceId'    => $plc->getId(),
                ],
                ]
            );
        }

        /// Person //////////////////////////////////////////////////////////////

        $psn = $this->gbs->newStorager(Person::class)->save(
            [
            'extracted' => true,
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#person=' . $r->id,
            ],
            'living'    => false,
            'gender'    => [
                'type'  => GenderType::MALE,
            ],
            'names' => [[
                'date'  => [
                    'original'  => $r->date,
                    'formal'    => $date_formal,
                ],
                'nameForms' => [[
                    'lang'      => 'ru',
                    'fullText'  => trim($r->surname . ', ' . $r->name, ', '),
                    'parts'     => [[
                        'type'  => NamePartType::SURNAME,
                        'value' => $r->surname,
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

        /// Events //////////////////////////////////////////////////////////////

        switch ($r->reason) {
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
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://1914.svrt.ru/#event=' . $r->id,
            ],
            'type'  => $event_type,
            'date'  => [
                'original'  => $r->date,
                'formal'    => $date_formal,
            ],
            'roles' => [[
                'type'      => \Gedcomx\Types\EventRoleType::PRINCIPAL,
                'person'    => [
                    'resourceId'    => $psn->getId(),
                ],
                'details'   => $r->reason,
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
    }
}
