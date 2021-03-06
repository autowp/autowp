<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\ItemHydrator as Hydrator;
use ArrayAccess;
use Exception;

class Item extends AbstractHydratorStrategy
{
    protected int $userId = 0;

    protected function getHydrator(): Hydrator
    {
        if (! isset($this->hydrator)) {
            $this->hydrator = new Hydrator($this->serviceManager);
        }

        /** @var Hydrator $result */
        $result = $this->hydrator;

        return $result;
    }

    /**
     * @param array|ArrayAccess $value
     * @throws Exception
     */
    public function extract($value): array
    {
        $hydrator = $this->getHydrator();

        $hydrator->setUserId($this->userId);
        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);

        return $hydrator->extract($value);
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }
}
