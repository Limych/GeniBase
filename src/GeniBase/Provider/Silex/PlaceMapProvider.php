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
namespace GeniBase\Provider\Silex;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;
use Twig_Environment;
use GeniBase\DBase\GeniBaseInternalProperties;

/**
 *
 * @author Limych
 *
 */
class PlaceMapProvider implements ServiceProviderInterface
{

    public function register(Container $app)
    {
        $app->extend('twig', function (Twig_Environment $twig, Application $app) {
            $twig->addFunction(new \Twig_SimpleFunction(
                'place_map',
                function ($place) use ($app) {
                    /** @var \Gedcomx\Conclusion\PlaceDescription $place */
                    $clat = $mlat = $place->getLatitude();
                    $clon = $mlon = $place->getLongitude();
                    $zoom = 11;

                    $bbox = GeniBaseInternalProperties::getPropertyOf($place, 'geo_bbox');
                    if (! empty($bbox[0])) {
                        $clat = ($bbox[0] + $bbox[2]) /2;
                        $clon = ($bbox[1] + $bbox[3]) /2;
                        $zoom = self::getBoundsZoomLevel($bbox[0], $bbox[1], $bbox[2], $bbox[3]);
                    }
//                     $mlat = $clat; // TODO Fix map showing and Remove this line
//                     $mlon = $clon; // TODO Fix map showing and Remove this line

                    $url = "https://maps.googleapis.com/maps/api/staticmap" .
                        "?center=$clat,$clon&zoom=$zoom&scale=2&size=640x320&maptype=terrain&format=png" .
                        "&style=feature:all|gamma:2" .
// Old fashioned style 1
//                         "&style=feature:road|feature:administrative|feature:poi|feature:labels|feature:transit|visibility:off" .
//                         "&style=feature:all|saturation:-30" .
//                         "&style=feature:water|saturation:-50" .
//                         "&style=feature:poi|visibility:off" .
//                         "&style=feature:road|visibility:off" .
//                         "&style=feature:road.arterial|visibility:on|color:0xF9F9F9" .
//                         "&style=feature:all|element:labels|visibility:off" .
//                         "&style=feature:administrative.locality|element:labels|visibility:simplified|color:0xAAAAAA" .
// Old fashioned style 2
//                         "&style=element:geometry%7Ccolor:0xebe3cd&style=element:labels.icon%7Cvisibility:off&style=element:labels.text.fill%7Ccolor:0x523735&style=element:labels.text.stroke%7Ccolor:0xf5f1e6&style=feature:administrative%7Celement:geometry.stroke%7Ccolor:0xc9b2a6&style=feature:administrative%7Celement:labels%7Ccolor:0xa38d69%7Cvisibility:simplified&style=feature:administrative.land_parcel%7Cvisibility:off&style=feature:administrative.land_parcel%7Celement:geometry.stroke%7Ccolor:0xdcd2be&style=feature:administrative.land_parcel%7Celement:labels.text.fill%7Ccolor:0xae9e90&style=feature:administrative.neighborhood%7Cvisibility:off&style=feature:landscape%7Celement:labels%7Ccolor:0xa38d69%7Cvisibility:simplified&style=feature:landscape.man_made%7Cvisibility:off&style=feature:landscape.natural%7Celement:geometry%7Ccolor:0xdfd2ae&style=feature:poi%7Celement:geometry%7Ccolor:0xdfd2ae&style=feature:poi%7Celement:labels.text%7Cvisibility:off&style=feature:poi%7Celement:labels.text.fill%7Ccolor:0x93817c&style=feature:poi.park%7Celement:geometry.fill%7Ccolor:0xa5b076&style=feature:poi.park%7Celement:labels.text.fill%7Ccolor:0x447530&style=feature:road%7Celement:geometry%7Ccolor:0xf5f1e6&style=feature:road%7Celement:labels%7Cvisibility:off&style=feature:road.arterial%7Celement:geometry%7Ccolor:0xfdfcf8&style=feature:road.arterial%7Celement:labels%7Cvisibility:off&style=feature:road.highway%7Celement:geometry.fill%7Ccolor:0xf1f1f1&style=feature:road.highway%7Celement:geometry.stroke%7Cvisibility:off&style=feature:road.local%7Celement:labels.text.fill%7Ccolor:0x806b63&style=feature:transit%7Cvisibility:off&style=feature:transit.line%7Celement:geometry%7Ccolor:0xdfd2ae&style=feature:transit.line%7Celement:labels.text.fill%7Ccolor:0x8f7d77&style=feature:transit.line%7Celement:labels.text.stroke%7Ccolor:0xebe3cd&style=feature:transit.station%7Celement:geometry%7Ccolor:0xdfd2ae&style=feature:water%7Celement:geometry.fill%7Ccolor:0xb9d3c2&style=feature:water%7Celement:labels.text%7Cvisibility:off&style=feature:water%7Celement:labels.text.fill%7Ccolor:0x92998d" .
                        "&markers=size:small%7Ccolor:red%7Clabel:%7C$mlat,$mlon";
                    if (! empty($app['api_key.google'])) {
                        $url .= '&key=' . $app['api_key.google'];
                        if (! empty($app['api_key.google.secret'])) {
                            $url = self::signUrl($url, $app['api_key.google.secret']);
                        }
                    }
                    return $url;
                },
                array('is_safe' => array('all'))
            ));

            $twig->addFunction(new \Twig_SimpleFunction(
                'place_dist',
                function ($place, $type = 'km') use ($app) {
                    /** @var \Gedcomx\Conclusion\PlaceDescription $place */
                    $dist = GeniBaseInternalProperties::getPropertyOf($place, 'geo_dist');
                    if ($type == 'verst') {
                        $dist *= 1.0668;
                    }
                    $dist = round($dist, ($dist >= 20 ? 0 : ($dist >= 10 ? 1 : 2)));
                    return $dist;
                },
                array('is_safe' => array('all'))
            ));

            return $twig;
        });
    }
    protected static function encodeBase64UrlSafe($value)
    {
        return str_replace(array('+', '/'), array('-', '_'), base64_encode($value));
    }

    protected static function decodeBase64UrlSafe($value)
    {
        return base64_decode(str_replace(array('-', '_'), array('+', '/'), $value));
    }

    protected static function signUrl($url, $privateKey)
    {
        // Parse the url
        $url = parse_url($url);

        $urlToSign =  $url['path'] . '?' . $url['query'];

        // Decode the private key into its binary format
        $decodedKey = self::decodeBase64UrlSafe($privateKey);

        // Create a signature using the private key and the URL-encoded
        // string using HMAC SHA1. This signature will be binary.
        $signature = hash_hmac('sha1', $urlToSign, $decodedKey, true);

        // Make encode Signature and make it URL Safe
        $encodedSignature = self::encodeBase64UrlSafe($signature);

        return $url['scheme'] . '://' . $url['host'] . $urlToSign . '&signature=' . $encodedSignature;
    }

    protected static function latRad($lat)
    {
        $sin = sin($lat * pi() / 180);
        $radX2 = log((1 + $sin) / (1 - $sin)) / 2;
        return max(min($radX2, pi()), -pi()) / 2;
    }

    protected static function zoom($mapPx, $worldPx, $fraction)
    {
        return floor(log($mapPx / $worldPx / $fraction, 2));
    }

    public static function getBoundsZoomLevel($lat1, $lon1, $lat2, $lon2, $mapWidth = 1100, $mapHeight = 180)
    {
        static $globeSize = 256;
        static $maxZoom = 21;

        $latAngle = max($lat1, $lat2) - min($lat1, $lat2) + 0.2;
        $lonAngle = max($lon1, $lon2) - min($lon1, $lon2) + 0.01;
        $latZoom = floor(log($mapHeight * 360 / $latAngle / $globeSize, 2));
        $lonZoom = floor(log($mapWidth * 360 / $lonAngle / $globeSize, 2));

        $zoom = min($maxZoom, $latZoom, $lonZoom);
// var_dump($latAngle, $lonAngle, $latZoom, $lonZoom, $zoom);die;   // FIXME Delete me
        return $zoom;
    }
}
