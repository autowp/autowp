<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\ItemHydrator as Hydrator;

class Item extends HydratorStrategy
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
