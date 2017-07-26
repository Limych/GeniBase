<?php
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
