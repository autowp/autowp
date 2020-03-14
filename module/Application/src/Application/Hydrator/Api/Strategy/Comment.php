<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\CommentHydrator as Hydrator;
use ArrayAccess;

class Comment extends AbstractHydratorStrategy
{
    protected int $userId;

    protected function getHydrator(): Hydrator
    {
        if (! isset($this->hydrator)) {
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
        $hydrator->setLanguage($this->language);
        $hydrator->setUserId($this->userId);

        return $hydrator->extract($value);
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }
}
