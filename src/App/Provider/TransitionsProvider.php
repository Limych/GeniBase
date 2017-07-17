<?php

namespace App\Provider;

use Symfony\Component\HttpFoundation\Response;

class TransitionsProvider
{
    /**
     *
     * @var array
     */
    protected $transitions;

    /**
     *
     */
    public function __construct()
    {
        $this->transitions = [];
    }

    /**
     *
     * @param string $uri
     * @param string|string[] $relations
     */
    public function addTransition($uri, $relations)
    {
        if (! is_array($relations))
            $relations = preg_split('/[\s,]+/', $relations, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($relations as $rel) {
            if (! isset($this->transitions[$uri]))
                $this->transitions[$uri] = [$rel];
            else
                $this->transitions[$uri][] = $rel;
        }
    }

    /**
     *
     * @param Response $response
     */
    public function setHeaders(Response $response)
    {
        $tr = [];
        foreach ($this->transitions as $uri => $rel) {
            $tr[] = "<$uri>; rel=\"" . join(' ', array_unique($rel)) . "\"";
        }
        if (! empty($tr))
            $response->headers->set('Link', join(', ', $tr));
    }
}
