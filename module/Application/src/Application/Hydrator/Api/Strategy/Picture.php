<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PictureHydrator as Hydrator;

class Picture extends HydratorStrategy
{
    /**
     * @return Hydrator
     */
    protected function getHydrator()
    {
        if (! $this->hydrator) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        return $this->hydrator;
    }

}
