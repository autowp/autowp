<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\AttrAttributeHydrator as Hydrator;
use ArrayAccess;

class AttrAttributes extends AbstractHydratorStrategy
{
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

        $result = [];
        foreach ($value as $row) {
            $result[] = $hydrator->extract($row);
        }
        return $result;
    }
}
