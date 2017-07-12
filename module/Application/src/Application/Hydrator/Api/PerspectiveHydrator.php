<?php

namespace Application\Hydrator\Api;

class PerspectiveHydrator extends RestHydrator
{
    public function extract($object)
    {
        $result = [
            'id'   => (int)$object['id'],
            'name' => $object['name']
        ];

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
