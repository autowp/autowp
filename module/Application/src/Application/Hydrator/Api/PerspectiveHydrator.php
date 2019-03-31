<?php

namespace Application\Hydrator\Api;

use Exception;

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
     * @param array $data
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
