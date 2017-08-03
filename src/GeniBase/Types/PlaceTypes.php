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
namespace GeniBase\Types;

/**
 * Enumeration of place types.
 *
 * @author Limych
 * @link https://en.wikipedia.org/wiki/List_of_administrative_divisions_by_country
 */
class PlaceTypes
{

    /**
     * Country or similar level place.
     */
    const COUNTRY = "//GeniBase/Country";

    /**
     * First level country division.
     *
     * Federal subject for Russia‎, State for the United States, Region for France, etc.
     *
     * @link https://en.wikipedia.org/wiki/Category:First-level_administrative_country_subdivisions
     */
    const FIRST_LEVEL = "//GeniBase/Country1stLevelSection";

    /**
     * Second level country division.
     *
     * District for Russia‎, County for the United States, Department for France, etc.
     *
     * @link https://en.wikipedia.org/wiki/Category:Second-level_administrative_country_subdivisions
     */
    const SECOND_LEVEL = "//GeniBase/Country2ndLevelSection";

    /**
     * Third level country division.
     *
     * Rural settlement for Russia‎, Municipality for the United States, Arrondissement for France, etc.
     *
     * @link https://en.wikipedia.org/wiki/Category:Third-level_administrative_country_subdivisions
     */
    const THIRD_LEVEL = "//GeniBase/Country3rdLevelSection";

    /**
     * City/Village level country division.
     */
    const SETTLEMENT = "//GeniBase/Settlement";
}
