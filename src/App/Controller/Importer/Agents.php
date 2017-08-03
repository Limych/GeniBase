<?php
namespace App\Controller\Importer;

use Gedcomx\Agent\Agent;
use GeniBase\Storager\GeniBaseStorager;

/**
 *
 * @author Limych
 *
 */
class Agents
{

    public static function getGeniBaseAgent(GeniBaseStorager $gbs)
    {
        return $gbs->newStorager(Agent::class)->save([
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => '//GeniBase/',
            ],
            'emails'    => [
                [   'resource'  => 'mailto:andrey@khrolenok.ru',       ],
            ],
            'names'    => [[
                'value' => 'GeniBase',
            ]],
        ]);
    }

    public static function getSvrtAgent(GeniBaseStorager $gbs)
    {
        return $gbs->newStorager(Agent::class)->save([
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
            'phones'    => [[
                'resource'  => 'tel:+7-925-367-25-95',
            ]],
            'names'    => [[
                'lang'  => 'ru',
                'value' => 'НП "Союз Возрождения Родословных Традиций" (СВРТ)',
            ]],
            'addresses'    => [[
                'country' => 'Russia',
                'postalCode' => '121096',
                'city' => 'г.Москва',
                'street' => '2-я Филевская ул., д.5, к.2',
            ]],
        ]);
    }

    public static function getRslAgent(GeniBaseStorager $gbs)
    {
        return $gbs->newStorager(Agent::class)->save([
            'identifiers'   => [
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://www.rsl.ru/',
            ],
            'homepage'  => [   'resource'  => 'http://www.rsl.ru/',    ],
            'emails'    => [[
                'resource'  => 'mailto:nbros@rsl.ru',
            ]],
            'phones'    => [
                [   'resource'  => 'tel:+7-800-100-57-90',          ],
                [   'resource'  => 'tel:+7-499-557-04-70;ext=2068', ],
                [   'resource'  => 'tel:+7-495-695-57-90',          ],
                [   'resource'  => 'tel:+7-495-690-60-62',          ],
            ],
            'names'    => [[
                'lang'  => 'ru',
                'value' => 'Федеральное государственное бюджетное учреждение «Российская государственная библиотека» (ФГБУ «РГБ»)',
            ]],
            'addresses'    => [[
                'country' => 'Russia',
                'postalCode' => '119019',
                'city' => 'г.Москва',
                'street' => 'ул. Воздвиженка, 3/5',
            ]],
        ]);
    }
}
