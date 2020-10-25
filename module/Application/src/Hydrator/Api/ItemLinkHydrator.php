<?php

namespace Application\Hydrator\Api;

use ArrayAccess;
use Exception;

class ItemLinkHydrator extends AbstractRestHydrator
{
    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
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
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object): void
    {
        throw new Exception("Not supported");
    }
}
