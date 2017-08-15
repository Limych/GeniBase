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
namespace App\Controller;

use Silex\Application;
use GeniBase\Storager\GeniBaseStorager;
use App\Util\Profiler;
use Symfony\Component\HttpFoundation\Response;

// TODO: For testing only. Remove that file

class TestController extends BaseController
{

    public function test(Application $app)
    {
        $tables = array_map(
            function ($v) use ($app) {
                return $app['gb.db']->getTableName($v);
            },
            preg_split('/[\s,]+/', GeniBaseStorager::TABLES_WITH_GBID, null, PREG_SPLIT_NO_EMPTY)
        );

        $ids = array(
            'A4PC-JAEF-9MV-',   // Unreal true nonexistetnt ID
            'YF93-J6P7-7379',   // Agent ID
            'CPV4-33NJ-R7XE',   // Source ID
            'CT46-36YP-KFE4',   // Person ID
            'N63H-4V97-V376',   // Event ID
            'L7HW-ANNP-V43N',   // Place ID
        );

//         $result = $app['db']->fetchAssoc("SELECT COUNT(*) AS agents FROM $t_agents");
        $attempts = 1000;
        /** @var \Doctrine\DBAL\Connection $db */
        $db = $app['db'];
        $max_id_i = count($ids) - 1;

        for ($i = 0; $i < $attempts; $i++) {
            Profiler::startTimer('MultyQuery');
            $gbid = $ids[rand(0, $max_id_i)];

            foreach ($tables as $tbl) {
                if (false !== $db->fetchColumn("SELECT 1 FROM $tbl WHERE id = ?", array( $gbid ))) {
                    continue 2;
                }
            }
        }
        Profiler::stopTimer('MultyQuery');

        for ($i = 0; $i < $attempts; $i++) {
            Profiler::startTimer('UnionQuery');
            $gbid = $ids[rand(0, $max_id_i)];

            $gbid = $db->quote($gbid);
            $q = implode(' UNION ALL ', array_map(function($tbl) use ($gbid) {
                return "SELECT 1 FROM $tbl WHERE id = $gbid";
            }, $tables));
            if (false !== $db->fetchColumn($q)) {
                continue;
            }
        }
        Profiler::stopTimer('UnionQuery');

        Profiler::dumpTimers();
        return new Response();
    }
}
