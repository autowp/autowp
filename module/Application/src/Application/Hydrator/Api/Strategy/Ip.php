<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\IpHydrator as Hydrator;
use ArrayAccess;

class Ip extends AbstractHydratorStrategy
{
    private int $userId;

    protected function getHydrator(): Hydrator
    {
        if (! $this->hydrator) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        return $this->hydrator;
    }

    /**
     * @param array|ArrayAccess $value
     */
    public function extract($value): array
    {
        $hydrator = $this->getHydrator();

        $hydrator->setUserId($this->userId);
        $hydrator->setFields($this->fields);

        return $hydrator->extract($value);
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }
}
