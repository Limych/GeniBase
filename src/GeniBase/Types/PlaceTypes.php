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
    const COUNTRY = "http://genibase.net/Country";

    /**
     * First level administrative country subdivision.
     *
     * Federal subject for Russia‎, State for the United States, Region for France, etc.
     *
     * @link https://en.wikipedia.org/wiki/Category:First-level_administrative_country_subdivisions
     */
    const AD1 = "http://genibase.net/AdministrativeDivision1";

    /**
     * Second level administrative country subdivision.
     *
     * District for Russia‎, County for the United States, Department for France, etc.
     *
     * @link https://en.wikipedia.org/wiki/Category:Second-level_administrative_country_subdivisions
     */
    const AD2 = "http://genibase.net/AdministrativeDivision2";

    /**
     * Third level administrative country subdivision.
     *
     * Rural settlement for Russia‎, Municipality for the United States, Arrondissement for France, etc.
     *
     * @link https://en.wikipedia.org/wiki/Category:Third-level_administrative_country_subdivisions
     */
    const AD3 = "http://genibase.net/AdministrativeDivision3";

    /**
     * Fourth level administrative country subdivision.
     *
     * @link https://en.wikipedia.org/wiki/Category:Fourth-level_administrative_country_subdivisions
     */
    const AD4 = "http://genibase.net/AdministrativeDivision4";

    /**
     * Fifth level administrative country subdivision.
     *
     * @link https://en.wikipedia.org/wiki/Category:Fifth-level_administrative_country_subdivisions
     */
    const AD5 = "http://genibase.net/AdministrativeDivision5";

    /**
     * Sixth level administrative country subdivision.
     *
     * @link https://en.wikipedia.org/wiki/Category:Sixth-level_administrative_country_subdivisions
     */
    const AD6 = "http://genibase.net/AdministrativeDivision6";

    /**
     * City/Village level country division.
     */
    const SETTLEMENT = "http://genibase.net/Settlement";
}
