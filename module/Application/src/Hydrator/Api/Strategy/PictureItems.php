<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PictureItemHydrator as Hydrator;
use ArrayAccess;

class PictureItems extends AbstractHydratorStrategy
{
    private int $userId = 0;

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
     */
    public function extract($value): array
    {
        $hydrator = $this->getHydrator();

        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);
        $hydrator->setUserId($this->userId);

        $result = [];
        foreach ($value as $row) {
            $result[] = $hydrator->extract($row);
        }
        return $result;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }
}
