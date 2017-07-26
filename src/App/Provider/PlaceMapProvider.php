<?php
namespace App\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 *
 * @author Limych
 *
 */
class PlaceMapProvider implements ServiceProviderInterface
{

    public function register(Container $app)
    {
        $app['twig'] = $app->extend('twig', function ($twig, $app) {
            $twig->addFunction(new \Twig_SimpleFunction(
                'place_map',
                function ($lat, $lon, $zoom = 11) use ($app) {
                    $url = "https://maps.googleapis.com/maps/api/staticmap?center=$lat,$lon&zoom=$zoom&scale=2&size=640x320&maptype=terrain&format=png&markers=size:small%7Ccolor:red%7Clabel:%7C$lat,$lon";
                    if (! empty($app['google_api.key'])) {
                        $url .= '&key=' . $app['google_api.key'];
                        if (! empty($app['google_api.secret'])) {
                            $url = self::signUrl($url, $app['google_api.secret']);
                        }
                    }
                    return $url;
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

    public static function getBoundsZoomLevel($lat1, $lon1, $lat2, $lon2, $mapWidth = 640, $mapHeight = 320)
    {
        static $globeWidth = 256;
        static $maxZoom = 21;

        $latAngle = max($lat1, $lat2) - min($lat1, $lat2);
        $lonAngle = max($lon1, $lon2) - min($lon1, $lon2);
        $angle = max($latAngle, $lonAngle);

        return min($maxZoom, floor(log(960 * 360 / $angle / $globeWidth, 2)));
    }
}
