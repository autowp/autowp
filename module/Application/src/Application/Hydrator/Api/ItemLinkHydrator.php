<?php

namespace Application\Hydrator\Api;

class ItemLinkHydrator extends RestHydrator
{
    public function extract($object)
    {
        return [
            'id'      => (int)$object['id'],
            'name'    => $object['name'],
            'url'     => $object['url'],
            'type_id' => $object['type']
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
