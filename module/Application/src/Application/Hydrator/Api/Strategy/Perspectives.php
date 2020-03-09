<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PerspectiveHydrator as Hydrator;
use ArrayAccess;

class Perspectives extends HydratorStrategy
{
    protected function getHydrator(): Hydrator
    {
        if (! $this->hydrator) {
            $this->hydrator = new Hydrator();
        }

        return $this->hydrator;
    }

    /**
     * @param array|ArrayAccess $value
     */
    public function extract($value): array
    {
        $hydrator = $this->getHydrator();

        $hydrator->setFields($this->fields);

        $result = [];
        foreach ($value as $row) {
            $result[] = $hydrator->extract($row);
        }
        return $result;
    }
}
