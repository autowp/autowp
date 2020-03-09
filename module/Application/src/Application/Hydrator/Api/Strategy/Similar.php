<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\SimilarHydrator as Hydrator;

class Similar extends HydratorStrategy
{
    protected function getHydrator(): Hydrator
    {
        if (! $this->hydrator) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        return $this->hydrator;
    }
}
