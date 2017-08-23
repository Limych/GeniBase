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
use Gedcomx\Rt\GedcomxModelVisitorBase;
use Carbon\Carbon;

class GedcomxMTimeCalculator extends GedcomxModelVisitorBase
{

    public $mtime;

    /**
     *
     * @param Gedcomx $document
     * @return Carbon
     */
    public static function getMTime(Gedcomx $document)
    {
        $visitor = new self();
        $document->accept($visitor);
        return Carbon::createFromTimestampUTC($visitor->mtime);
    }

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitAttribution()
     */
    protected function visitAttribution(\Gedcomx\Common\Attribution $attribution)
    {
        if (! empty($res = $attribution->getModified()) && ($this->mtime < $res)) {
            $this->mtime = $res;
        }

        parent::visitAttribution($attribution);
    }
}
