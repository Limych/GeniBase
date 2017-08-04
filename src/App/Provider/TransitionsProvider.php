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
namespace App\Provider;

use Symfony\Component\HttpFoundation\Response;

class TransitionsProvider
{
    /**
     *
     * @var array
     */
    protected $transitions;

    /**
     *
     */
    public function __construct()
    {
        $this->transitions = [];
    }

    /**
     *
     * @param string          $uri
     * @param string|string[] $relations
     */
    public function addTransition($uri, $relations)
    {
        if (! is_array($relations)) {
            $relations = preg_split('/[\s,]+/', $relations, -1, PREG_SPLIT_NO_EMPTY);
        }

        foreach ($relations as $rel) {
            if (! isset($this->transitions[$uri])) {
                $this->transitions[$uri] = [$rel];
            } else {
                $this->transitions[$uri][] = $rel;
            }
        }
    }

    /**
     *
     * @param Response $response
     */
    public function setHeaders(Response $response)
    {
        $tr = [];
        foreach ($this->transitions as $uri => $rel) {
            $tr[] = "<$uri>; rel=\"" . join(' ', array_unique($rel)) . "\"";
        }
        if (! empty($tr)) {
            $response->headers->set('Link', join(', ', $tr));
        }
    }
}
