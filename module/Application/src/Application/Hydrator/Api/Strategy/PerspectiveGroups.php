<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\PerspectiveGroupHydrator as Hydrator;
use ArrayAccess;

class PerspectiveGroups extends AbstractHydratorStrategy
{
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

        $hydrator->setFields($this->fields);

        $result = [];
        foreach ($value as $row) {
            $result[] = $hydrator->extract($row);
        }
        return $result;
    }
}
