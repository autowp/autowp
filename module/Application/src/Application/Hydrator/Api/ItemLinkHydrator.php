<?php

namespace Application\Hydrator\Api;

use Exception;

class ItemLinkHydrator extends RestHydrator
{
    public function extract($object)
    {
        return [
            'id'      => (int) $object['id'],
            'name'    => $object['name'],
            'url'     => $object['url'],
            'type_id' => $object['type'],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
