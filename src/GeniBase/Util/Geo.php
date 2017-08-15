<?php
namespace GeniBase\Util;

/**
 *
 * @author Limych
 *
 */
class Geo
{

    public static function box($src_lat, $src_lon, $dist)
    {
        $lat_top = $src_lat + ($dist / 69);
        $lon_lft = $src_lon - ($dist / abs(cos(deg2rad($src_lat)) * 69));
        $lat_bot = $src_lat - ($dist / 69);
        $lon_rgt = $src_lon + ($dist / abs(cos(deg2rad($src_lat)) * 69));

        return array($lat_top, $lon_lft, $lat_bot, $lon_rgt);
    }

    public static function dist($src_lat, $src_lon, $dst_lat, $dst_lon)
    {
        $dist = 6371 * 2 * asin(sqrt(
            pow(sin(($src_lat - abs($dst_lat)) * pi() / 180 / 2), 2) +
            cos($src_lat * pi() / 180) *
            cos(abs($dst_lat) * pi() / 180) *
            pow(sin(($src_lon - $dst_lon) * pi() / 180 / 2), 2)
        ));
        return $dist;
    }
}
