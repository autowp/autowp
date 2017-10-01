<?php

namespace Application\Hydrator\Api\Strategy;

use Application\Hydrator\Api\ForumTopicHydrator as Hydrator;

class ForumTopics extends HydratorStrategy
{
    private $userId;

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

        $hydrator->setFields($this->fields);
        $hydrator->setLanguage($this->language);
        $hydrator->setUserId($this->userId);

        $result = [];
        foreach ($value as $row) {
            $result[] = $hydrator->extract($row);
        }
        return $result;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }
}
