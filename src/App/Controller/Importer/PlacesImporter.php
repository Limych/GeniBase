<?php
namespace App\Controller\Importer;

use App\Util;
use Gedcomx\Conclusion\PlaceDescription;
use Gedcomx\Util\FormalDate;
use GeniBase\Storager\GeniBaseStorager;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class PlacesImporter
{

    const OVERTIME_COOKIE   = 'PlacesImporter';

    protected $app;
    protected $gbs;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->gbs = new GeniBaseStorager($app['gb.db']);
    }

    protected function parsePlace($raw, $parentPlace)
    {
        $place = [
            'confidence'    => \Gedcomx\Types\ConfidenceLevel::HIGH,
        ];

        // Parse for dates of existence
        if (preg_match("/(.*?)\s*\{(.+)\}\s*(.*)/", $raw, $matches)) {
            $tmp = new FormalDate();
            $tmp->parse($matches[2]);
            $place['temporalDescription'] = [];
            if ($tmp->isValid()) {
                $place['temporalDescription']['formal'] = $matches[2];
            } else {
                $place['temporalDescription']['original'] = $matches[2];
            }
            $raw = $matches[1] . $matches[3];
        }

        // Parse for geographical coordinates
        if (preg_match("/(.*?)\s*\[([\d.]*),\s*([\d.]*)\]\s*(.*)/", $raw, $matches)) {
            if (! empty($matches[2]) && ! empty($matches[3])) {
                $place['latitude'] = floatval($matches[2]);
                $place['longitude'] = floatval($matches[3]);
            }
            $raw = $matches[1] . $matches[4];
        }

        // Parse for variations of name
        if (preg_match("/(.*?)\s*\(([^\)]+)\)(.*)/", $raw, $matches)) {
            $tmp = preg_split("/[;,]\s*/", $matches[2], null, PREG_SPLIT_NO_EMPTY);
            array_unshift($tmp, $matches[1]);
            foreach ($tmp as $y) {
                $place['names'][] = [
                    'lang'  => 'ru',
                    'value' => $y . $matches[3],
                ];
            }
        } else {
            $place['names'][] = [
                'lang'  => 'ru',
                'value' => $raw,
            ];
        }

        if (isset($parentPlace)) {
            $place['jurisdiction'] = [
                'resourceId'    => $parentPlace->getId(),
            ];
        }

        return $place;
    }

    public function import(Request $request)
    {
        $start = $request->cookies->getInt(self::OVERTIME_COOKIE);

        $places = $this->getPlaces();
        $count = count($places);

        for ($cnt = $start; $cnt < $count; $cnt++) {
            if (Util::executionTime() > 10000) {
                $response = new Response("<progress value='$cnt' max='$count'>$cnt records</progress>");
                $response->headers->setCookie(new Cookie(self::OVERTIME_COOKIE, $cnt));
                $response->headers->set('Refresh', '0; url=' . $request->getUri());
                return $response;
            }

            /** @var PlaceDescription $plc */
            unset($plc);
            $x = preg_split("/\s+>\s+/", $places[$cnt], null, PREG_SPLIT_NO_EMPTY);
            foreach ($x as $v) {
                $place = $this->parsePlace($v, $plc);
                $plc = $this->gbs->newStorager(PlaceDescription::class)->save($place);
            }
        }

        $url = $this->app['url_generator']->generate('api_statistic');
        $subRequest = Request::create($url);
        $response = $this->app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

        $response->headers->clearCookie(self::OVERTIME_COOKIE);
        return $response;
    }

    protected function getPlaces($year = 1913)
    {
        $fpath = BASE_DIR . "/var/places_{$year}.txt";

        $places = file_get_contents($fpath);

        if (false !== $places) {
            $places = preg_split("/[\r\n]+/", $places, null, PREG_SPLIT_NO_EMPTY);
        }

        return $places;
    }
}
