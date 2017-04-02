<?php

namespace Application\Hydrator\Api\Strategy;

use Interop\Container\ContainerInterface;

use Autowp\Image\Storage;

use Application\Hydrator\Api\UserHydrator as Hydrator;

class User extends HydratorStrategy
{
    /**
     * @var int|null
     */
    protected $userId = null;

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
        //$hydrator->setFields($this->fields);
        
        return $hydrator->extract($value);
    }

    public function hydrate($value)
    {
        return null;
    }
    
    public function setUserId($userId)
    {
        $this->userId = $userId;
    
        return $this;
    }
}
