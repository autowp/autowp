<?php

namespace Application\Hydrator\Api;

use ArrayAccess;
use Exception;

class ItemParentLanguageHydrator extends AbstractRestHydrator
{
    /**
     * @param array|ArrayAccess $object
     */
    public function extract($object): ?array
    {
        return [
            'language' => $object['language'],
            'name'     => $object['name'],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }
}
