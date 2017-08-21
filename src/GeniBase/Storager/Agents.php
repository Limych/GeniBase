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

class Agents
{

    public static function getGeniBaseAgent(GeniBaseStorager $gbs)
    {
        return $gbs->newStorager('Gedcomx\Agent\Agent')->save(array(
            'identifiers' => array(
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://genibase.net/',
            ),
            'emails' => array(
                array( 'resource' => 'mailto:andrey@khrolenok.ru', ),
            ),
            'names' => array(array(
                'value' => 'GeniBase',
            )),
        ));
    }

    public static function getSvrtAgent(GeniBaseStorager $gbs)
    {
        return $gbs->newStorager('Gedcomx\Agent\Agent')->save(array(
            'identifiers' => array(
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://www.svrt.ru/',
            ),
            'homepage' => array( 'resource' => 'http://www.svrt.ru/', ),
            'emails' => array(
                array( 'resource' => 'mailto:svrtinfo@mail.ru',    ),
                array( 'resource' => 'mailto:bibikov2002@mail.ru', ),
                array( 'resource' => 'mailto:strigan1@yandex.ru',  ),
                array( 'resource' => 'mailto:n-lobodina@mail.ru',  ),
            ),
            'phones' => array(array(
                'resource' => 'tel:+7-925-367-25-95',
            )),
            'names' => array(array(
                'lang' => 'ru',
                'value' => 'НП "Союз Возрождения Родословных Традиций" (СВРТ)',
            )),
            'addresses' => array(array(
                'country'    => 'Russia',
                'postalCode' => '121096',
                'city'       => 'г.Москва',
                'street'     => '2-я Филевская ул., д.5, к.2',
            )),
        ));
    }

    public static function getRslAgent(GeniBaseStorager $gbs)
    {
        return $gbs->newStorager('Gedcomx\Agent\Agent')->save(array(
            'identifiers' => array(
                \Gedcomx\Types\IdentifierType::PERSISTENT => 'http://www.rsl.ru/',
            ),
            'homepage' => array( 'resource' => 'http://www.rsl.ru/', ),
            'emails' => array(array(
                'resource' => 'mailto:nbros@rsl.ru',
            )),
            'phones' => array(
                array( 'resource' => 'tel:+7-800-100-57-90',          ),
                array( 'resource' => 'tel:+7-499-557-04-70;ext=2068', ),
                array( 'resource' => 'tel:+7-495-695-57-90',          ),
                array( 'resource' => 'tel:+7-495-690-60-62',          ),
            ),
            'names' => array(array(
                'lang' => 'ru',
                'value' => 'Федеральное государственное бюджетное учреждение «Российская государственная библиотека» (ФГБУ «РГБ»)',
            )),
            'addresses' => array(array(
                'country'    => 'Russia',
                'postalCode' => '119019',
                'city'       => 'г.Москва',
                'street'     => 'ул. Воздвиженка, 3/5',
            )),
        ));
    }
}
