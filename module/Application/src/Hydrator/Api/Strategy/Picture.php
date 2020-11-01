<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PictureHydrator as Hydrator;

class Picture extends AbstractHydratorStrategy
{
    protected function getHydrator(): Hydrator
    {
        if (! isset($this->hydrator)) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        /** @var Hydrator $result */
        $result = $this->hydrator;

        return $result;
    }
}
