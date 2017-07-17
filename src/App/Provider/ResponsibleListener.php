<?php

/*
 * This file is part of ResponsibleServiceProvider.
 *
 * (c) Tobias Sjösten <tobias@tobiassjosten.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Provider;

use Gedcomx\GedcomxFile\DefaultJsonSerialization;
use Gedcomx\GedcomxFile\DefaultXMLSerialization;
use GeniBase\Common\GeniBaseClass;
use Silex\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

class ResponsibleListener implements EventSubscriberInterface
{
    protected $app;
    protected $encoder;

    public function __construct(Application $app, EncoderInterface $encoder)
    {
        $this->app = $app;
        $this->encoder = $encoder;
    }

    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();
        $result = $event->getControllerResult();

        if (! is_array($result) && ! ($result instanceof \Gedcomx\Gedcomx)
        && ! ($result instanceof GeniBaseClass)) {
            return;
        }

        $supported = array('json', 'xml');
        $default = reset($supported);
        $accepted = $request->getAcceptableContentTypes() ?: array($request->getMimeType($default));

        // If Accept is blank, then */* is the only option available.
        // Change it to the current Content-Type to attempt returning the format received
        if (count($accepted) === 1 && $accepted[0] === '*/*') {
            $accepted[0] = $request->headers->get('Content-Type');
        }

        foreach ($accepted as $type) {
            if (in_array($format = $request->getFormat($type), $supported)) {
                if ($result instanceof \Gedcomx\Gedcomx || $result instanceof GeniBaseClass) {
                    $serializer = ('json' == $format
                        ? new DefaultJsonSerialization() : new DefaultXMLSerialization() );
                    $response = new Response(
                        $serializer->serialize($result),
                        200,
                        array('Content-Type' => $type)
                        );
                    $event->setResponse($response);

                } else {
                    $event->setResponse(new Response(
                        $this->encoder->encode($result, $format),
                        200,
                        array('Content-Type' => $type)
                    ));
                }

                return;
            }
        }

        // HTTP/1.1 recommends returning some data over giving a 406 error,
        // even if that data is not supported by the Accept header.
        if ('HTTP/1.1' === $request->server->get('SERVER_PROTOCOL')) {
            $event->setResponse(new Response(
                $this->encoder->encode($result, $default),
                200,
                array('Content-Type' => $request->getMimeType($default))
            ));

            return;
        }

        $event->setResponse(new Response(
            'Not Acceptable',
            406,
            array('Content-Type' => 'text/plain')
        ));
    }

    public static function getSubscribedEvents()
    {
        return array(KernelEvents::VIEW => array('onKernelView', -10));
    }
}