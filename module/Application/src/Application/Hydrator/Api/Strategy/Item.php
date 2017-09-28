<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\ItemHydrator as Hydrator;

class Item extends HydratorStrategy
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
        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);

        return $hydrator->extract($value);
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }
}
