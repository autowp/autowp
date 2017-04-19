<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\IpHydrator as Hydrator;

class Ip extends HydratorStrategy
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
    
    public function extract($value)
    {
        $hydrator = $this->getHydrator();
    
        $hydrator->setUserId($this->userId);
        $hydrator->setFields($this->fields);
    
        return $hydrator->extract($value);
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    
        return $this;
    }
}
