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
use Gedcomx\Common\TextValue;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Rt\GedcomxModelVisitorBase;
use Gedcomx\Util\FormalDate;
use Gedcomx\Util\SimpleDate;
use GeniBase\Util\SimpleDateFormatter;

/**
 *
 * @author Limych
 */
class GedcomxRsUpdater extends GedcomxModelVisitorBase
{

    /**
     *
     * @param Gedcomx $document
     * @return \Gedcomx\Gedcomx
     */
    public static function update(Gedcomx $document)
    {
        $visitor = new self();
        $document->accept($visitor);
        return $document;
    }

    /**
     * Visits the place description.
     *
     * @param \Gedcomx\Conclusion\PlaceDescription $place
     */
    public function visitPlaceDescription(PlaceDescription $place)
    {
        /** @var DateInfo $res */
        if (! empty($res = $place->getTemporalDescription())) {
            array_push($this->contextStack, $place);
            $res->accept($this);
            array_pop($this->contextStack);
        }

        parent::visitPlaceDescription($place);
    }

    /**
     *
     * @param SimpleDate $date
     * @return string
     */
    protected static function makeDate(SimpleDate $date)
    {
        $result = '';

        if (! empty($res = $date->getDay())) {
            $result .= sprintf('%02d', $res);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     * @see \Gedcomx\Rt\GedcomxModelVisitorBase::visitDate()
     */
    public function visitDate(DateInfo $date)
    {
        $tv1 = new TextValue([   'lang'  => 'ru' ]);

        if (! empty($d = $date->getFormal())) {
            $tv2 = new TextValue([   'lang'  => 'ru' ]);

            $fd = new FormalDate();
            $fd->parse($d);

            $r1 = $r2 = '';
            if (! empty($res = $fd->getStart())) {
                $r1 .= $res->getYear();
                $r2 .= SimpleDateFormatter::format($res);
            } else {
                $r1 .= 'Н/Д';
                $r2 .= 'Н/Д';
            }
            if ($fd->getIsRange()) {
                $r1 .= '–';
                $r2 = "c $r2 по ";
                if (! empty($res = $fd->getEnd())) {
                    $r1 .= $res->getYear();
                    $r2 .= SimpleDateFormatter::format($res);
                } elseif ($fd->getIsRange()) {
                    $r1 .= 'н/в';
                    $r2 .= 'н/в';
                }
            }

            $tv1->setValue($r1);
            $tv2->setValue(ucfirst($r2));
        } else {
            $tv1->setValue($date->getOriginal());
            $tv2 = $tv1;
        }

        $date->setNormalizedExtensions([$tv1, $tv2]);

        parent::visitDate($date);
    }
}
